<?php
require 'functions.php';
class Controller {

	private $twig;
    protected $MAX_UPLOAD_SIZE;

	public function __construct() {
        $this->MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
        setlocale(LC_ALL,'en_US.UTF-8');
		$whoops = new \Whoops\Run;
		$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
		$whoops->register();
		$loader = new Twig_Loader_Filesystem('templates');
		$this->twig = new Twig_Environment($loader, []);

	}

	protected function render($file,array $arr)
	{
		echo $this->twig->render($file, $arr);
	}

}