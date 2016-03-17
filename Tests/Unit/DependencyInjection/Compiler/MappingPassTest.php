<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\Tests\Unit\DependencyInjection\Compiler;

use ONGR\ElasticsearchBundle\DependencyInjection\Compiler\MappingPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for MappingPass.
 */
class MappingPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Before a test method is run, a template method called setUp() is invoked.
     */
    public function testProcessWithSeveralManagers()
    {
        $metadataCollectorMock = $this->getMockBuilder('ONGR\ElasticsearchBundle\Mapping\MetadataCollector')
            ->disableOriginalConstructor()
            ->getMock();

        $metadataCollectorMock->expects($this->once())->method('getMappings')->willReturn(
            [
                'product' => [
                    'properties' => [],
                    'bundle' => 'AcmeBarBundle',
                    'class' => 'Foo',
                    'namespace' => 'Acme\BarBundle\Document\Foo',
                ],
            ]
        );

        $connections = [
            'default' => [
                'hosts' => ['127.0.0.1:9200'],
                'index_name' => 'acme',
                'settings' => [
                    'refresh_interval' => -1,
                    'number_of_replicas' => 1,
                ],
            ],
        ];

        $managers = [
            'default' => [
                'connection' => 'default',
                'debug' => true,
                'mappings' => ['AcmeBarBundle'],
            ],
        ];

        $containerMock = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->expects($this->exactly(3))->method('getParameter')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) use ($connections, $managers) {
                        switch ($parameter) {
                            case 'es.managers':
                                return $managers;
                            case 'es.connections':
                                return $connections;
                            default:
                                return null;
                        }
                    }
                )
            );

        $containerMock->expects($this->once())->method('get')->with($this->anything())
            ->will(
                $this->returnCallback(
                    function ($parameter) use ($metadataCollectorMock) {
                        switch ($parameter) {
                            case 'es.metadata_collector':
                                return $metadataCollectorMock;
                            default:
                                return null;
                        }
                    }
                )
            );
        $containerMock
            ->expects($this->exactly(2))
            ->method('setDefinition')
            ->withConsecutive(
                [$this->equalTo('es.manager.default')],
                [$this->equalTo('es.manager.default.product')]
            )
            ->willReturn(null);

        $compilerPass = new MappingPass();
        $compilerPass->process($containerMock);
    }

    /**
     * Test exception is thrown in case invalid connection name configured.
     *
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage There is no ES connection with the name
     */
    public function testInvalidConnectionException()
    {
        $managers = [
            'default' => [
                'connection' => 'default',
                'debug' => true,
                'mappings' => ['AcmeBarBundle'],
            ],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('es.analysis', null);
        $container->setParameter('es.connections', []);
        $container->setParameter('es.managers', $managers);

        $metadataCollectorMock = $this->getMockBuilder('ONGR\ElasticsearchBundle\Mapping\MetadataCollector')
            ->disableOriginalConstructor()
            ->getMock();

        $container->set('es.metadata_collector', $metadataCollectorMock);

        $compilerPass = new MappingPass();
        $compilerPass->process($container);
    }
}
