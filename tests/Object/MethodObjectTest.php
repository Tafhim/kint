<?php

namespace Kint\Test\Object;

use Kint\Object\BasicObject;
use Kint\Object\InstanceObject;
use Kint\Object\MethodObject;
use PHPUnit_Framework_TestCase;
use ReflectionFunction;
use ReflectionMethod;
use stdClass;

class MethodObjectTest extends PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $reflection = new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'mix');
        $m = new MethodObject($reflection);
        $this->assertEquals('mix', $m->name);
        $this->assertEquals($reflection->getFilename(), $m->filename);
        $this->assertEquals($reflection->getStartLine(), $m->startline);
        $this->assertEquals($reflection->getEndLine(), $m->endline);
        $this->assertEquals(false, $m->internal);
        $this->assertEquals($reflection->getDocComment(), $m->docstring);
        $this->assertEquals(BasicObject::OPERATOR_STATIC, $m->operator);
        $this->assertEquals(BasicObject::ACCESS_PROTECTED, $m->access);
        $this->assertEquals('Kint\\Test\\Stub\\TestClass', $m->owner_class);
        $this->assertTrue($m->static);
        $this->assertTrue($m->final);
        $this->assertFalse($m->abstract);
        $this->assertFalse($m->internal);

        $reflection = new ReflectionMethod('Kint\\Test\\Stub\\ChildTestClass', '__construct');
        $parent_reflection = new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__construct');
        $m = new MethodObject($reflection);
        $this->assertEquals($parent_reflection->getDocComment(), $m->docstring);
        $this->assertEquals(BasicObject::OPERATOR_OBJECT, $m->operator);
        $this->assertEquals(BasicObject::ACCESS_PUBLIC, $m->access);
        $this->assertEquals('Kint\\Test\\Stub\\TestClass', $m->owner_class);

        $reflection = new ReflectionFunction('explode');
        $m = new MethodObject($reflection);
        $this->assertTrue($m->internal);
        $this->assertEquals(BasicObject::OPERATOR_NONE, $m->operator);
        $this->assertEquals(BasicObject::ACCESS_NONE, $m->access);
        $this->assertEquals(null, $m->owner_class);
    }

    public function testConstructWrongType()
    {
        if (KINT_PHP70) {
            $this->setExpectedException('TypeError');
        } else {
            $this->setExpectedException('PHPUnit_Framework_Error');
        }
        $m = new MethodObject(new stdClass());
    }

    public function testSetAccessPathFrom()
    {
        $o = BasicObject::blank('$tc');
        $o = $o->transplant(new InstanceObject());
        $o->classname = 'Kint\\Test\\Stub\\TestClass';

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__construct'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('new \\Kint\\Test\\Stub\\TestClass()', $m->getAccessPath());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'static_method'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('\\Kint\\Test\\Stub\\TestClass::static_method()', $m->getAccessPath());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'final_method'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('$tc->final_method()', $m->getAccessPath());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'mix'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals(
            '\\Kint\\Test\\Stub\\TestClass::mix(array &$x, Kint\\Test\\Stub\\TestClass $y = null, $z = array(...), $_ = \'string\')',
            $m->getAccessPath()
        );

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__clone'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('clone $tc', $m->getAccessPath());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__invoke'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('$tc($x)', $m->getAccessPath());

        // Tests both tostring and case insensitivity
        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__tostring'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertEquals('__ToStRiNg', $m->name);
        $this->assertEquals('(string) $tc', $m->getAccessPath());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__get'));
        $this->assertNull($m->getAccessPath());
        $m->setAccessPathFrom($o);
        $this->assertNull($m->getAccessPath());
    }

    public function testGetValueShort()
    {
        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', '__construct'));
        $this->assertEquals(
            'This is a constructor for a TestClass with the first line of the docstring split into two different lines.',
            $m->getValueShort()
        );
    }

    public function testGetModifiers()
    {
        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'static_method'));
        $this->assertEquals('private static', $m->getModifiers());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'final_method'));
        $this->assertEquals('final public', $m->getModifiers());

        $m = new MethodObject(new ReflectionMethod('ReflectionFunctionAbstract', '__toString'));
        $this->assertEquals('abstract public', $m->getModifiers());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'mix'));
        $this->assertEquals('final protected static', $m->getModifiers());
    }

    public function testGetAccessPath()
    {
        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'array_hint'));
        $this->assertNull($m->getAccessPath());
        $m->access_path = '$m->array_hint';
        $this->assertEquals('$m->array_hint(array $x)', $m->getAccessPath());
    }

    public function testGetParams()
    {
        $m = new MethodObject(new ReflectionFunction('explode'));
        if (defined('HHVM_VERSION')) {
            $this->assertStringStartsWith('HH\\string $delimiter, HH\\string $str, HH\\int $limit = ', $m->getParams());
        } else {
            $this->assertEquals('$separator, $str, $limit', $m->getParams());
        }

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'array_hint'));
        $this->assertEquals('array $x', $m->getParams());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'class_hint'));
        $this->assertEquals('Kint\\Test\\Stub\\TestClass $x', $m->getParams());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'ref'));
        $this->assertEquals('&$x', $m->getParams());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'default_method'));
        $this->assertEquals('$x = 1234', $m->getParams());

        $m = new MethodObject(new ReflectionMethod('Kint\\Test\\Stub\\TestClass', 'mix'));
        $this->assertEquals(
            'array &$x, Kint\\Test\\Stub\\TestClass $y = null, $z = array(...), $_ = \'string\'',
            $m->getParams()
        );
    }

    public function testGetPhpDocUrl()
    {
        $m = new MethodObject(new ReflectionMethod('ReflectionMethod', '__construct'));
        $this->assertEquals(
            'https://secure.php.net/reflectionmethod.construct',
            $m->getPhpDocUrl()
        );
    }

    public function testGetPhpDocUrlParent()
    {
        $m = new MethodObject(new ReflectionMethod('ReflectionMethod', '__clone'));
        $this->assertEquals(
            'https://secure.php.net/reflectionfunctionabstract.clone',
            $m->getPhpDocUrl()
        );
    }

    public function testGetPhpDocUrlUserDefined()
    {
        $m = new MethodObject(new ReflectionMethod(__CLASS__, __FUNCTION__));
        $this->assertNull($m->getPhpDocUrl());
    }
}