<?php
/**
 * Implements publish command.
 */
class Publish_Command {

    const SEMVERSIONS = array('patch', 'minor', 'major');

    const VERSIONREG  = '[0-9]+\.[0-9]+\.[0-9]+';
    
    /**
     * Publish version of site and theme
     * 
     * ## OPTIONS
     * <version>...
     * : Version or subcommand followed by version number
     * [--dryrun=<dryrun>]
     * : whether to actually update files, or run a dry run with not actual file changes.
     * ---
     * default: false
     * options:
     *    - true
     *    - false
     * ---
     * [--message=<message>]
     * : release notes if full editor is not needed
     * [--repository=<repository>]
     * : Git repository url for changelog compare link
     * 
     * ## EXAMPLES
     *    wp publish major|minor|patch|1.0.0
     *    wp publish site major|minor|patch|1.0.0
     *    wp publish theme <theme> major|minor|patch|1.0.0
     *    wp publish plugin <plugin> major|minor|patch|1.0.0
     */
    function __invoke( $args, $assoc_args ) {
        $this->command = count($args) == 1 ? '' : $args[0];
        $this->folder  = count($args) != 3 ? '' : $args[1];
        $this->version = end($args);

        $this->message    = $assoc_args['message'] ?? '';
        $this->repository = $assoc_args['repository'] ?? '';

        if ( !$this->command ) $this->command = $this->get_command();

        $method = 'publish_'.$this->command;
        
        if ( !method_exists( $this, $method ) ) WP_CLI::error("{$this->command} command not found");
        
        $updated = $this->$method();

        if ( !$updated ) WP_CLI::error('Unable to publish version');

        WP_CLI::success( $updated );
    }

    function get_command() {
        $plugin = $this->get_plugin($this->folder);
        if ( $plugin ) return 'plugin';

        $theme = $this->get_theme($this->folder);
        if ( $theme ) return 'theme';

        return 'site';
    }

    function get_message($message, $current, $new) {
        if ($this->message) return $this->message;

        $message = WP_CLI\Utils\launch_editor_for_input("\n\n# Please enter the release notes.\n# Lines starting with '#' will be ignored and an empty message aborts the release.\n# Updating {$this->command}: {$message}\n# Current version: v{$current}\n# Releasing version: v{$new}");
        $message = preg_replace('/^#[^#].*$\s+/m', '', $message);
        $message = trim($message);

        $this->message = $message;
    }

    function version_check($current, $new) {
        if ( !preg_match('/^'.self::VERSIONREG.'/', $new) ) WP_CLI::error('invalid version option');

        if ( version_compare($current, $new) !== -1 ) WP_CLI::error("New version number: {$new} is below current version: {$current}");
    }

    function parse_theme_folder() {
        if (strpos(get_theme_root(), getcwd()) === false) return basename(getcwd());

        $relative = str_replace(trailingslashit(get_theme_root()), '', getcwd());
        $pieces   = explode('/', $relative);
        return $pieces[0] ?? false;
    }

    function get_theme($folder=false) {
        if ( !$folder ) $folder = $this->parse_theme_folder();

        $fetcher = new WP_CLI\Fetchers\Theme();

        return $fetcher->get( $folder );
    }

    function get_theme_version($folder=false) {
        $theme = $this->get_theme($folder);

        return $theme ? $theme->get('Version') : false;
    }

    function get_theme_folder($folder=false) {
        $theme = $this->get_theme($folder);

        return $theme ? $theme->get_stylesheet_directory() : false;
    }

    function parse_plugin_folder() {
        if (strpos(WP_PLUGIN_DIR, getcwd()) === false) return basename(getcwd());

        $relative = str_replace(trailingslashit(WP_PLUGIN_DIR), '', getcwd());
        $pieces   = explode('/', $relative);
        return $pieces[0] ?? false;
    }

    function get_plugin($folder=false) {
        if ( !$folder ) $folder = $this->parse_plugin_folder();

        $fetcher = new WP_CLI\Fetchers\Plugin();
        $plugin  = $fetcher->get( $folder );

        if ( !$plugin ) return $this->get_mu_plugin($folder);

        $data = get_plugin_data(trailingslashit(WP_PLUGIN_DIR) . $plugin->file, false);

        return array_merge($data, [
            'PluginFile'   => trailingslashit(WP_PLUGIN_DIR) . $plugin->file,
            'PluginFolder' => trailingslashit(WP_PLUGIN_DIR) . $folder
        ]);
    }

    function get_mu_plugin_data($file) {
        $default_headers = array(
            'Name'        => 'Plugin Name',
            'PluginURI'   => 'Plugin URI',
            'Version'     => 'Version',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'TextDomain'  => 'Text Domain',
            'DomainPath'  => 'Domain Path',
            'Network'     => 'Network',
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
            'UpdateURI'   => 'Update URI',
        );
     
        return get_file_data( $file, $default_headers, 'plugin' );
    }

    function get_mu_plugin($folder=false) {
        if ( !$folder ) $folder = $this->parse_plugin_folder();

        if ( !$folder ) return;

        foreach ( get_mu_plugins() as $file => $plugin ) {
            if ( file_exists(trailingslashit(WPMU_PLUGIN_DIR) . trailingslashit($folder) . $file) ) {
                $data = $this->get_mu_plugin_data(trailingslashit(WPMU_PLUGIN_DIR) . trailingslashit($folder) . $file);
                return array_merge($data, [
                    'PluginFile'   => trailingslashit(WPMU_PLUGIN_DIR) . trailingslashit($folder) . $file,
                    'PluginFolder' => trailingslashit(WPMU_PLUGIN_DIR) . trailingslashit($folder)
                ]);
            }
        }
    }

    function get_plugin_version($folder=false) {
        $plugin = $this->get_plugin($folder);

        return $plugin['Version'] ?? false;
    }

    function get_plugin_folder($folder=false) {
        $plugin = $this->get_plugin($folder);

        return $plugin['PluginFolder'] ?? false;
    }

    function get_plugin_file($folder=false) {
        $plugin = $this->get_plugin($folder);

        return $plugin['PluginFile'] ?? false;
    }

    protected function update_file($path, $find, $replace, $count=1){
        if ( !file_exists($path) ) return WP_CLI::line( "{$path} doesn't exist" );

        $contents = file_get_contents($path);

        if (!$contents) return WP_CLI::line( "{$path} is empty" );

        $updated = preg_replace($find, $replace, $contents, $count);

        if (!$updated || $contents == $updated) return WP_CLI::line( "{$path} failed or wasn't changed" );

        $saved = file_put_contents($path, $updated);
        
        return ($saved !== false);
    }

    function update_style($folder, $current, $new){
        $path     = trailingslashit($folder) . 'style.css';
        $find     = '/^(\s*Version:\s?)'.$current.'$/m';
        $replace  = '${1}'.$new;
        return $this->update_file($path, $find, $replace);
    }

    function update_changelog($folder, $current, $new){
        if ( !$this->message ) $this->get_message(basename($folder), $current, $new);

        if ( !$this->message ) WP_CLI::error('Aborting release due to empty release notes');

        $date    = current_time('Y-m-d');
        $path    = trailingslashit( $folder ) . 'changelog.md';
        $find    = '/^(## \[?v'.$current.'\]?((\()(.+\/)([^)]+)(\)))?)/m';
        $link    = $this->repository ? "" : "v{$new}";
        $replace = "## [v{$new}]\\3\\4v{$current}...v{$new}\\6 {$date}\n{$this->message}\n\n\\1";
        return $this->update_file($path, $find, $replace);
    }

    function update_composer($folder, $current, $new){
        $path    = trailingslashit( $folder ) . 'composer.json';
        $find     = '/^(\s*"version": ")'.$current.'(",?)$/m';
        $replace  = '${1}'.$new.'${2}';
        return $this->update_file($path, $find, $replace);
    }

    function update_plugin($path, $current, $new) {
        $find     = '/^(\s.*Version[:\'],?\s?\'?)'.$current.'(\'\);)?$/mi';
        $replace  = '${1}'.$new.'${2}';
        $this->update_file($path, $find, $replace, 2);
    }

    function publish_plugin() {
        $file    = $this->get_plugin_file($this->folder);
        $root    = $this->get_plugin_folder($this->folder);
        $current = $this->get_plugin_version($this->folder);
        $new     = WP_CLI\Utils\increment_version($current, $this->version);
        
        $this->version_check($current, $new);

        $this->update_changelog($root, $current, $new);
        $this->update_composer($root, $current, $new);
        $this->update_plugin($file, $current, $new);

        return "Plugin updated from {$current} to {$new}";
    }

    function publish_theme() {
        $root    = $this->get_theme_folder($this->folder);
        $current = $this->get_theme_version($this->folder);
        $new     = WP_CLI\Utils\increment_version($current, $this->version);

        $this->version_check($current, $new);

        $changelog = $this->update_changelog($root, $current, $new);
        $style     = $this->update_style($root, $current, $new);
        $composer  = $this->update_composer($root, $current, $new);

        if ( !$changelog && !$style && !$composer ) return WP_CLI::error('Release failed');

        return "Theme updated from {$current} to {$new}";
    }

    function publish_site() {
        $theme   = wp_get_theme();
        $current = $theme->get('Version');
        $new     = WP_CLI\Utils\increment_version($current, $this->version);

        $this->version_check($current, $new);

        $changelog = $this->update_changelog(ABSPATH, $current, $new);
        $style     = $this->update_style($theme->get_stylesheet_directory(), $current, $new);

        if ( !$changelog && !$style ) return WP_CLI::error('Release failed');

        return "Site updated from {$current} to {$new}";
    }
}

WP_CLI::add_command( 'publish', 'Publish_Command' );