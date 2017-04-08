<?php
require_once 'vendor/autoload.php';

class Manager extends Controller
{

    //Security options
    private $login = false;
    private $password = '';  // Set the password, to access the file manager... (optional)
    private $allow_delete = true; // Set to false to disable delete button and delete POST request.
    private $allow_create_folder = true; // Set to false to disable folder creation
    private $allow_upload = true; // Set to true to allow upload files
    private $allow_direct_link = true; // Set to false to only allow downloads and not direct link
    private $disallowed_extensions = ['php'];  // must be an array.
    private $dev = true;


    public function index()
    {
        if ($this->dev) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
        $error = null;
        $path = null;
        $folder = null;
        try {
            //$this->security();
            $path = $this->handle($_GET);
            @$folder = explode('/',$path)[1];
            $this->action();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }


        return $this->render('view.html.twig', ['error' => $error, 'path' => $path, 'folder' => $folder]);
    }


    private function handle(array $param)
    {
        extract($param);
        $path = null;
        if (isset($name)) {
            if (isset($isZip) && $isZip == true) {
                $path = $this->unzip($name);
            } elseif (isset($zip) && $zip == true) {
                $this->zip($name);
            }
        }
        return $path;
    }

    private function zip($dir)
    {
        $rootPath = realpath('tmp/'.$dir);

        $zip = new ZipArchive();
        $zip->open($dir.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);


        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function unzip($name)
    {
        $dir = explode('.', $name)[0];
        $zip = new ZipArchive;
        $res = $zip->open($name);
        if ($res === TRUE) {
            $path = 'tmp/' . $dir;
            $zip->extractTo($path);
            $zip->close();
            return $path;
        } else {
            throw new Exception('Zip cannot be opened!');
        }
    }

    public function security()
    {
        $tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);

        if (DIRECTORY_SEPARATOR === '\\') {
            $tmp_dir = str_replace('/', DIRECTORY_SEPARATOR, $tmp_dir);
        }
        $file = $_REQUEST['file'] ?? '';
        $tmp = get_absolute_path($tmp_dir . '/' . $file);

        if ($tmp === false) {
            err(404, 'File or Directory Not Found');
        }
        if (stripos($tmp, $tmp_dir) !== false) {
            err(403, 'Forbidden');
        }
        if (!$_COOKIE['_sfm_xsrf']) {
            setcookie('_sfm_xsrf', bin2hex(openssl_random_pseudo_bytes(16)));
        }
        if ($_POST) {
            if ($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf']) {
                err(403, 'XSRF Failure');
            }
        }

        if ($this->login === true) {
            session_start();
            if (!$_SESSION['_sfm_allowed']) {
                // sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
                $t = bin2hex(openssl_random_pseudo_bytes(10));
                if ($_POST['password'] && sha1($t . $_POST['password']) === sha1($t . $this->password)) {
                    $_SESSION['_sfm_allowed'] = true;
                    header('Location: ?');
                }
                return $this->render('login.html', []);
            }
        }
    }

    public function list_files($file)
    {

        if (is_dir($file)) {
            $directory = $file;
            $result = [];
            $files = array_diff(scandir($directory), ['.', '..', '.DS_Store']);
            foreach ($files as $entry) {
                $i = $directory . '/' . $entry;
                $stat = stat($i);

                $result[] = [
                    'mtime' => $stat['mtime'],
                    'size' => $stat['size'],
                    'name' => basename($i),
                    'path' => preg_replace('@^\./@', '', $i),
                    'is_dir' => is_dir($i),
                    'is_deleteable' => $this->allow_delete && ((!is_dir($i) && is_writable($directory)) ||
                            (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i))),
                    'is_readable' => is_readable($i),
                    'is_writable' => is_writable($i),
                    'is_executable' => is_executable($i),
                ];
            }
            echo json_encode(['success' => true, 'is_writable' => is_writable($file), 'results' => $result]);
            return $result;
        }
    }


    public function delete($file)
    {
        if ($this->allow_delete === true) {
            rmrf($file);
        }
    }

    public function mkdir($file)
    {
        // don't allow actions outside root. we also filter out slashes to catch args like './../outside'
        $dir = $_POST['name'];
        $dir = str_replace('/', '', $dir);
        if (substr($dir, 0, 2) === '..')
            exit;
        chdir($file);
        mkdir($_POST['name']);
    }

    public function upload($file)
    {
        var_dump($_POST);
        var_dump($_FILES);
        var_dump($_FILES['file_data']['tmp_name']);
        foreach ($this->disallowed_extensions as $ext) {
            if (preg_match(sprintf('/\.%s$/', preg_quote($ext)), $_FILES['file_data']['name'])) {
                err(403, 'Files of this type are not allowed.');
            }
        }
        var_dump(move_uploaded_file($_FILES['file_data']['tmp_name'], $file . '/' . $_FILES['file_data']['name']));
    }

    public function download($file)
    {
        $filename = basename($file);
        header('Content-Type: ' . mime_content_type($file));
        header('Content-Length: ' . filesize($file));
        header(sprintf('Content-Disposition: attachment; filename=%s', strpos('MSIE', $_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\""));
        ob_flush();
        readfile($file);
    }

    public function action()
    {
        $file = dirname($_SERVER['SCRIPT_FILENAME']);

        if (isset($_REQUEST['file']) && !empty($_REQUEST['file'])) {
            $file = $_REQUEST['file'];
        }
        $do = $_REQUEST['do'] ?? null;

        switch ($do) {
            case 'list':
                $this->list_files($file);
                exit;
            case 'delete':
                $this->delete($file);
                exit;
            case 'mkdir':
                $this->mkdir($file);
                exit;
            case 'upload':
                $this->upload($file);
                exit;
            case 'download':
                $this->download($file);
                exit;
        }
    }

    private function pretty($arr)
    {
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
        die;
    }

    public function __destruct()
    {
        $this->index();
    }

}

$manager = new Manager;
