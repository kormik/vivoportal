<?php
namespace Vivo\Fake;

class Document extends \Vivo\CMS\Model\Document {
	
	protected $layout, $path;
	
	public function __construct() {
//		parent::__construct();
		$this->name = "DOC";
	}
	
	public function getLayout() {
		return $this->layout;
	}
	
	public function getContents() {
		return array();
	}
	
	public function getPath() {
		return $this->path;
	}
	
	public function setPath($path) {
		$this->path = $path;
	}
	
}
