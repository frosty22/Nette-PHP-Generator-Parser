<?php

namespace PhpGenerator;

/**
 * Parser of PHP files with classes 
 * 
 * @author Vít Ledvinka
 * 
 */
class Parser {
	
	/**
	 * Filename with class
	 * @var string
	 */
	private $filename;
	
	/**
	 * Content of filename
	 * @var string
	 */
	private $content;
	
	/**
	 * PHP file
	 * @var \PhpGenerator\PhpFile 
	 */
	private $phpfile;
	
	/**
	 * Constructor for load file
	 * @param string $filename
	 * @throws \Nette\FileNotFoundException 
	 */
	public function __construct($filename)
	{
		if (!file_exists($filename))
			throw new \Nette\FileNotFoundException("File '$filename' not found.");
		
		$this->filename = $filename; 
		require_once $this->filename; 
	}

	/**
	 * Get PHP file object
	 * @return type 
	 */
	public function getPhpFile()
	{
		if (!isset($this->phpfile)) {
			$this->phpfile = new PhpGenerator\PhpFile();

			$classes = $this->getPhpClasses(); 
			foreach ($classes as $namespace => $classes) {
				foreach ($classes as $class) {
					$this->phpfile->addClassType($this->getClassType($namespace ? $namespace . "\\" . $class : $class), $namespace ? $namespace : null);
				}	
			}
		}
		return $this->phpfile;
	}
	
	/**
	 * Parse class from file
	 * @param string $classname
	 * @return \Nette\Utils\PhpGenerator\ClassType 
	 */
	protected function getClassType($classname)
	{
		$reflection = new \ReflectionClass($classname);
		
		$classtype = new \Nette\Utils\PhpGenerator\ClassType($reflection->getShortName());
		$classtype->setAbstract($reflection->isAbstract());
		$classtype->setFinal($reflection->isFinal());
		if ($reflection->getParentClass()) $classtype->addExtend($reflection->getParentClass()->getName());
		
		$interfaces = $reflection->getInterfaces();
		foreach ($interfaces as $interface) $classtype->addImplement($interface->getName());
		
		$docComment = $this->parseDocComment($reflection->getDocComment());
		if ($docComment) foreach ($docComment as $row) $classtype->addDocument($row);
		
		$constants = $reflection->getConstants(); 
		foreach ($constants as $name => $value) { 
			$classconstant = $classtype->addConst($name, $value);
		}
		
		$properties = $reflection->getDefaultProperties();
		foreach ($properties as $property => $value) {
			$propertyReflection = $reflection->getProperty($property);
			$classproperty = $classtype->addProperty($property, $value);
			$classproperty->setVisibility($this->getVisibilityName($propertyReflection));
			$classproperty->setStatic($propertyReflection->isStatic());
		
			$docComment = $this->parseDocComment($propertyReflection->getDocComment());
			if ($docComment) foreach ($docComment as $row) $classproperty->addDocument($row);
		}
		
		$methods = $reflection->getMethods();
		foreach ($methods as $method) { 
			if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
				$classmethod = $classtype->addMethod($method->getName());
				$classmethod->setAbstract($method->isAbstract());
				$classmethod->setStatic($method->isStatic());
				$classmethod->setFinal($method->isFinal());
				$classmethod->setVisibility($this->getVisibilityName($method));
				
				$parameters = $method->getParameters();
				foreach ($parameters as $parameter) { 
					$methodparameter = $classmethod->addParameter($parameter->getName());
					$methodparameter->setReference($parameter->isPassedByReference());
					$methodparameter->setTypeHint($this->getTypeHint($parameter));	
					if ($parameter->isDefaultValueAvailable()) 
						$methodparameter->setOptional(true)->setDefaultValue($parameter->getDefaultValue());
				}
				
				$docComment = $this->parseDocComment($method->getDocComment());
				if ($docComment) foreach ($docComment as $row) $classmethod->addDocument($row);
				
				$classmethod->setBody($this->getBodyOfMethod($method));
			}
		}
		
		return $classtype;
	}
	
	/**
	 * Get list of class with namespace
	 * @return array 
	 */
	protected function getPhpClasses() {
		$classes = array();
		
		$namespace = 0;  
		$tokens = token_get_all(Implode("", $this->getContent())); 
		$count = count($tokens); 
		$dlm = false;
		for ($i = 2; $i < $count; $i++) { 
			if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] == "phpnamespace" || $tokens[$i - 2][1] == "namespace")) || 
				($dlm && $tokens[$i - 1][0] == T_NS_SEPARATOR && $tokens[$i][0] == T_STRING)) { 
				if (!$dlm) $namespace = 0; 
				if (isset($tokens[$i][1])) {
					$namespace = $namespace ? $namespace . "\\" . $tokens[$i][1] : $tokens[$i][1];
					$dlm = true; 
				}	
			}		
			elseif ($dlm && ($tokens[$i][0] != T_NS_SEPARATOR) && ($tokens[$i][0] != T_STRING)) {
				$dlm = false; 
			} 
			if (($tokens[$i - 2][0] == T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] == "phpclass")) 
					&& $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
				$class_name = $tokens[$i][1]; 
				if (!isset($classes[$namespace])) $classes[$namespace] = array();
				$classes[$namespace][] = $class_name;
			}
		} 
		return $classes;
	}

	/**
	 * Get content of file
	 * @return string
	 */
	protected function getContent()
	{
		if (!isset($this->content)) $this->content = file($this->filename, \FILE_IGNORE_NEW_LINES);
		return $this->content;
	}
	
	/**
	 * Get type hint of parameter 
	 * @param \ReflectionParameter $parameter
	 * @return null|string
	 */
	protected function getTypeHint(\ReflectionParameter $parameter)
	{
		$export = \ReflectionParameter::export(
			array(
				$parameter->getDeclaringClass()->name, 
				$parameter->getDeclaringFunction()->name
			), 
			$parameter->name, 
			true
		);

		if (preg_match('~\> (.*?) \$~', $export, $match)) 
			return $match[1];
		else 
			return null;
	}
	
	/**
	 * Parse block of doc comment
	 * @param string $doc Doc block
	 * @return null|string
	 */
	protected function parseDocComment($doc)
	{ 
		$return = Explode("\n", trim(preg_replace('~(\s)*(\/\*\*|\*\/?)(\s)*~', "\n", $doc)));
		return empty($return) ? null : ($return[0] === "" ? null : $return);
	}
	
	/**
	 * Get name if visibility from reflection (only shortcut for calling method)
	 * @param mixed $reflection Reflection object
	 * @return string 
	 */
	protected function getVisibilityName($reflection)
	{
		if ($reflection->isPrivate()) return "private";
		if ($reflection->isProtected()) return "protected";
		if ($reflection->isPublic()) return "public";
	}
	
	/**
	 * Get body of method
	 * @param \ReflectionMethod $method
	 * @return string 
	 */
    protected function getBodyOfMethod(\ReflectionMethod $method)
    {
        $lines = array_slice($this->getContent(), $method->getStartLine(), ($method->getEndLine() - $method->getStartLine()), true);

        $firstLine = array_shift($lines);

        if (trim($firstLine) !== '{') {
            array_unshift($lines, $firstLine);
        }

        $lastLine = array_pop($lines);

        if (trim($lastLine) !== '}') {
            array_push($lines, $lastLine);
        }

        // just in case we had code on the bracket lines
        return rtrim(ltrim(implode("\n", $lines), '{'), '}');		
    }


	
}


