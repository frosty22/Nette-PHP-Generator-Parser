<?php

namespace Nette\Utils\PhpGenerator;

/**
 * PHP File content - list of classes
 * 
 * @author Vít Ledvinka
 * 
 */
class PhpFile extends \Nette\Object
{
	
	/**
	 * List of classes in file
	 * @var array of ClassType
	 */
	public $classes = array();
	
	/**
	 * Add classtype to file
	 * @param \Nette\Utils\PhpGenerator\ClassType $classType 
	 * @param string $namespace
	 */
	public function addClassType(\Nette\Utils\PhpGenerator\ClassType $classType, $namespace = null)
	{
		if (!$namespace) $namespace = 0;
		if (!isset($this->classes[$namespace])) $this->classes[$namespace] = array();
		$this->classes[$namespace][] = $classType;
	}
	
	/**
	 * PHP Code
	 * @return string 
	 */
	public function __toString() {
		$html = "";
		foreach ($this->classes as $namespace => $classes) {
			if (!$namespace && count($this->classes) > 1) { 
				$html .= \Nette\Utils\Strings::normalize(
					"namespace {\n\n" . implode("\n\n\n", $classes) . "\n\n\n}"
					) . "\n\n\n";				
			} else {
				$html .= \Nette\Utils\Strings::normalize(
					($namespace ? "namespace ".$namespace.";\n\n" : "")
					. implode("\n\n\n", $classes)
					) . "\n\n\n";
			}	
		}
		return $html;
	}
	
	
}




