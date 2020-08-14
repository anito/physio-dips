<?php
namespace App\Controller\Api;

use App\Controller\Api\AppController;
use Cake\Cache\Cache;
use Cake\Log\Log;


class KodaksController extends AppController
{

    public function initialize() {
        parent::initialize();
        define('USE_X_SEND', false);
        Cache::disable();
        $this->Auth->allow([]);
        $this->loadComponent( 'File' );
        $this->loadComponent( 'Salt' );
        $this->loadComponent( 'Director' );

    }

    private function n($var, $default = false) {
        $var = trim($var);
        if (is_numeric($var)) {
            return $var;
        } else if ( isset( $default ) ) {
            return $default;
        } else {
            exit;
        }
    }

    public function process() {

        $val = $this->getRequest()->getParam( 'crypt' );
        $timestamp = $this->getRequest()->getParam( 'timestamp' );

        if ( strpos( $val, 'http://' ) !== false || substr($val, 0, 1) == '/' ) {
            header('Location: ' . $val);
            exit;
        } else {
            $val = str_replace(' ', '.2B', $val);
        }


        $val = str_replace( ' ', '.2B', $val );
        $crypt = $this->Salt->convert( $val, false ); // decrypt
        $a = explode(',', $crypt);
        
        $file = $fn = basename($a[0]);

        // Make sure supplied filename contains only approved chars
        if (preg_match("/[^A-Za-z0-9._-]/", $file)) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }

        $id     = $a[1];
        $w      = $this->n($a[2]);
        $h      = $this->n($a[3]);
        $sq     = $this->n($a[4]);
        $q      = $this->n($a[5], 100);
        $sh     = $this->n($a[6], 0);
        $x      = $this->n($a[7], 50);
        $y      = $this->n($a[8], 50);
        $force  = $this->n($a[9], 0);

        $ext = $this->File->returnExt( $file );

        define('PATH', $this->Director->getPathConstant($fn));
        if (!defined('PATH')) {
            return;
        }

        $original = PATH . DS . $id . DS . 'lg' . DS . $file;

        if ($this->File->isVideo($file)) $sq = 2;

        if ( $sq==2 ) {
            $base_dir = PATH . DS . $id . DS . 'lg';
            $path_to_cache = $original;
        } else {
            $fn .= "_{$w}_{$h}_{$sq}_{$q}_{$sh}_{$x}_{$y}";
            $fn .= ".$ext";
            $base_dir = PATH . DS . $id . DS . 'cache';
            $path_to_cache = $base_dir . DS . $fn;
        }

        // Make sure dirname of the cached copy is sane
        if (dirname($path_to_cache) !== $base_dir) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }

        $noob = false;

        if (!file_exists($path_to_cache)) {
            $noob = true;
            if ($sq == 2) {
                copy($original, $path_to_cache);
            } else {
                if (!defined('MAGICK_PATH')) {
                    if (!defined('MAGICK_PATH_FINAL'))
                        define('MAGICK_PATH_FINAL', 'convert');
                } else if (strpos(strtolower(MAGICK_PATH), 'c:\\') !== false) {
                    define('MAGICK_PATH_FINAL', '"' . MAGICK_PATH . '"');
                } else {
                    define('MAGICK_PATH_FINAL', MAGICK_PATH);
                }
                if (!defined('FORCE_GD')) {
                    define('FORCE_GD', 0);
                }
                if (!is_dir(dirname($path_to_cache))) {
                    $parent_perms = substr(sprintf('%o', fileperms(dirname(dirname($path_to_cache)))), -4);
                    $old = umask(0);
                    mkdir(dirname($path_to_cache), octdec($parent_perms));
                    umask($old);
                }

                $this->loadComponent( 'Darkroom' );
                $this->Darkroom->develop($original, $path_to_cache, $w, $h, $sq, $q, $x, $y, $force);
            }
        }

        $mtime = filemtime($path_to_cache);
        $etag = md5($path_to_cache . $mtime);

        if (!$noob) {
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && ($_SERVER['HTTP_IF_NONE_MATCH'] == $etag)) {
                header("HTTP/1.1 304 Not Modified");
                exit;
            }

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($path_to_cache))) {
                header("HTTP/1.1 304 Not Modified");
                exit;
            }
        }

        if (USE_X_SEND) {
            header("X-Sendfile: $path_to_cache");
        } else {
            // $specs = getimagesize($path_to_cache);
            // header('Content-type: ' . $specs['mime']);
            header('Content-type: ' . mime_content_type( $path_to_cache));
            header('Content-length: ' . filesize($path_to_cache));
            header('Cache-Control: public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+1 year')));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime));
            header('ETag: ' . $etag);
        }

        die(file_get_contents($path_to_cache));
    }
    
}