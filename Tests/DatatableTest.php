<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sg\DatatablesBundle\Datatable\AbstractDatatable;
use Sg\DatatablesBundle\Tests\Datatables\PostDatatable;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class DatatableTest
 *
 * @internal
 * @coversNothing
 */
final class DatatableTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCreate()
    {
        $tableClass = PostDatatable::class;

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $securityToken        = $this->createMock(TokenStorageInterface::class);
        $translator           = $this->createMock(TranslatorInterface::class);
        $router               = $this->createMock(RouterInterface::class);
        $twig                 = $this->createMock(Environment::class);

        $em = $this->getMockBuilder(EntityManager::class)
                   ->disableOriginalConstructor()
                   ->setMethods(
                       ['getClassMetadata'],
                   )
                   ->getMock();

        // @noinspection PhpUndefinedMethodInspection
        $em->expects(self::any())
           ->method('getClassMetadata')
           ->willReturn($this->getClassMetadataMock());

        $table = new $tableClass($authorizationChecker, $securityToken, $translator, $router, $em, $twig);

        self::assertSame('post_datatable', $table->getName());

        $table->buildDatatable();
    }

    /**
     * @throws ReflectionException
     */
    public function testInvalidName()
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $securityToken        = $this->createMock(TokenStorageInterface::class);
        $translator           = $this->createMock(TranslatorInterface::class);
        $router               = $this->createMock(RouterInterface::class);
        $twig                 = $this->createMock(Environment::class);

        $em = $this->getMockBuilder(EntityManager::class)
                   ->disableOriginalConstructor()
                   ->setMethods(
                       ['getClassMetadata'],
                   )
                   ->getMock();

        // @noinspection PhpUndefinedMethodInspection
        $em->expects(self::any())
           ->method('getClassMetadata')
           ->willReturn($this->getClassMetadataMock());

        $mock = $this->getMockBuilder(AbstractDatatable::class)
                     ->disableOriginalConstructor()
                     ->setMethods(['getName'])
                     ->getMockForAbstractClass();
        $mock->expects(self::any())
             ->method('getName')
             ->willReturn('invalid.name');

        $refledtionClass = new ReflectionClass(AbstractDatatable::class);
        $constructor     = $refledtionClass->getConstructor();
        $this->expectException(LogicException::class);
        $constructor->invoke($mock, $authorizationChecker, $securityToken, $translator, $router, $em, $twig);
    }

    /**
     * @return (ClassMetadata&MockObject)|MockObject
     */
    public function getClassMetadataMock()
    {
        $mock = $this->getMockBuilder(ClassMetadata::class)
                     ->disableOriginalConstructor()
                     ->setMethods(['getEntityShortName'])
                     ->getMock();

        // @noinspection PhpUndefinedMethodInspection
        $mock->expects(self::any())
             ->method('getEntityShortName')
             ->willReturn('{entityShortName}');

        return $mock;
    }
}
