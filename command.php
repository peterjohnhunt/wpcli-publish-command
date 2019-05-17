<?php
/**
 * Implements publish command.
 */
class Publish_Command {

    const COMMANDS = array('patch', 'minor', 'major');

    const VPATTERN = '[0-9]+\.[0-9]+\.[0-9]+';

    protected $theme;

    public function __construct() {
        $this->theme = $this->_get_active_theme();
    }

    public function __invoke( $args ) {
        list( $command ) = $args;

        if ( preg_match('/^'.self::VPATTERN.'/', $command) ){
            $version = $command;
        } elseif ( in_array($command, self::COMMANDS) ) {
            $version = $this->_uptick_version( $command );
        } else {
            WP_CLI::error('incorrect version command');
        }

        $updated = $this->set_version($version);

        if ( !$updated ) WP_CLI::error('unable to update version');

        WP_CLI::success( "Version updated to {$version}" );

        return $version;
    }

    protected function _get_active_theme(){
        return wp_get_theme();
    }

    protected function _get_current_version(){
        return $this->theme ? $this->theme->version : false;
    }

    protected function _get_stylesheet_dir(){
        return $this->theme ? $this->theme->get_stylesheet_directory() : false;
    }

    protected function _uptick_version($type){
        $version = $this->_get_current_version();

        $pieces = explode('.', $version);

        if (count($pieces) != 3) WP_CLI::error('Unable to parse theme version');

        list($major, $minor, $patch) = $pieces;

        if ( in_array($type, array('major', 'minor')) ){
            $patch = '0';
        }

        if ( $type == 'major' ){
            $minor = '0';
        }
        
        if ($type == 'major'){
            $major++;
        } elseif ($type == 'minor'){
            $minor++;
        } elseif ($type == 'patch'){
            $patch++;
        }

        return implode('.', array($major, $minor, $patch));
    }

    protected function _update_style($filename, $pattern, $replace, $count=1){
        $base = $this->_get_stylesheet_dir();
        $path = trailingslashit($base) . $filename;

        if ( !file_exists($path) ) return WP_CLI::line( "{$filename} doesn't exist or is empty" );

        $contents = file_get_contents($path);

        if (!$contents) return WP_CLI::line( "{$filename} doesn't exist or is empty" );

        $updated  = preg_replace($pattern, $replace, $contents, $count);

        if (!$updated || $contents == $updated) return WP_CLI::line( "{$filename} failed or wasn't changed" );

        $saved = file_put_contents($path, $updated);
        
        return ($saved !== false);
    }

    protected function set_version($new){
        $old  = $this->_get_current_version();

        if ( version_compare($old,$new) !== -1 ) WP_CLI::error("New version number: {$new} is below current version: {$old}");

        $pattern = '/^(\s*Version:\s?)'.self::VPATTERN.'$/m';

        return $this->_update_style('style.css', $pattern, '${1}'.$new);
    }
}

WP_CLI::add_command( 'publish', 'Publish_Command' );