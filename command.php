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

        $this->set_version($version);

        WP_CLI::success( 'version published' );
    }

    protected function _get_active_theme(){
        $raw_themes = WP_CLI::launch_self( 'theme list', array(), array( 'format' => 'json' ), false, true );
        $themes     = json_decode( $raw_themes->stdout );
        $active     = array_filter($themes, function($t){ return $t->status == 'active'; });

        if ( empty($active) ) WP_CLI::error('no active theme');

        $active = array_shift($active);

        $raw_theme = WP_CLI::launch_self( 'theme get '.$active->name, array(), array( 'format' => 'json' ), false, true );
        $theme = json_decode( $raw_theme->stdout );

        return $theme;
    }

    protected function _get_current_version(){
        return !empty($this->theme) ? $this->theme->version : false;
    }

    protected function _get_stylesheet_dir(){
        return !empty($this->theme) ? $this->theme->stylesheet_dir : false;
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

    protected function _update_file($filename, $pattern, $replace, $count=1){
        $base     = $this->_get_stylesheet_dir();
        $path     = trailingslashit($base) . $filename;
        $contents = file_get_contents($path);

        if (!$contents) return WP_CLI::line( "{$filename} doesn't exist or is empty" );

        $updated  = preg_replace($pattern, $replace, $contents, $count);

        if (!$updated || $contents == $updated) return WP_CLI::line( "{$filename} failed or wasn't changed" );
        
        return file_put_contents($path, $updated);
    }

    protected function set_version($new){
        $old  = $this->_get_current_version();

        if ( version_compare($old,$new) !== -1 ) WP_CLI::error("New version number: {$new} is below current version: {$old}");

        $pattern = '/([Vv]ersion:\s?)'.self::VPATTERN.'/';
        $style   = $this->_update_file('style.css', $pattern, '${1}'.$new);
        if ( $style ) WP_CLI::line( "updated style.css version to {$new}" );

        $pattern   = '/([\'\"]THEME_VERSION[\'\"],\s?[\'\"])'.self::VPATTERN.'([\'\"])/';
        $functions = $this->_update_file('functions.php', $pattern, '${1}'.$new.'${2}');
        if ( $functions ) WP_CLI::line( "updated functions.php version to {$new}" );
    }
}

WP_CLI::add_command( 'publish', 'Publish_Command' );