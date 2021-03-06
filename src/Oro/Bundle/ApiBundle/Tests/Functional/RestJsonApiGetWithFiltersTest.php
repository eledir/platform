<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional;

use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity\TestEmployee;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadBusinessUnit;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\UserBundle\Entity\User;

class RestJsonApiGetWithFiltersTest extends RestJsonApiTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loadFixtures([
            LoadOrganization::class,
            LoadBusinessUnit::class,
            '@OroApiBundle/Tests/Functional/DataFixtures/filters.yml'
        ]);
    }

    /**
     * @param string       $entityClass          The FQCN of an entity
     * @param integer      $expectedStatusCode   expected status code of a response
     * @param array        $params               request parameters
     * @param array|string $expects              response expectation
     * @param string|null  $idsReplacementMethod method to be used for ids correction
     * @param string       $identifier           attribute name value to reach referenced object
     *
     * @dataProvider getParamsAndExpectation
     */
    public function testGetEntity(
        $entityClass,
        $expectedStatusCode,
        $params,
        $expects,
        $idsReplacementMethod = null,
        $identifier = null
    ) {
        $entityType = $this->getEntityType($entityClass);

        $response = $this->request(
            'GET',
            $this->getUrl('oro_rest_api_cget', ['entity' => $entityType]),
            $params
        );

        self::assertApiResponseStatusCodeEquals($response, $expectedStatusCode, $entityType, 'get list');

        $expects = $this->loadResponseData($expects);
        if ($idsReplacementMethod && $identifier) {
            $expects = $this->{$idsReplacementMethod}($expects, $identifier);
        }
        $this->assertResponseContains($expects, $response);
    }

    /**
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getParamsAndExpectation()
    {
        return [
            'filter by field'                                                                              => [
                'className'  => 'Oro\Bundle\UserBundle\Entity\User',
                'statusCode' => 200,
                'params'     => [
                    'filter' => [
                        'email' => 'admin@example.com'
                    ],
                    'fields' => [
                        'users' => 'phone,title,username,email,firstName,middleName,lastName,enabled'
                    ]
                ],
                'expects'    => 'output_1.yml'
            ],
            'filter by field of 2nd level related entity (user.owner)'                                     => [
                'className'  => 'Oro\Bundle\UserBundle\Entity\User',
                'statusCode' => 200,
                'params'     => [
                    'filter' => [
                        'owner.name' => 'Main'
                    ],
                    'fields' => [
                        'users' => 'phone,title,username,email,firstName,middleName,lastName,enabled'
                    ]
                ],
                'expects'    => 'output_1.yml'
            ],
            'filter by field of 3rd level related entity (user.owner.organization)'                        => [
                'className'    => 'Oro\Bundle\UserBundle\Entity\User',
                'statusCode'   => 200,
                'params'       => [
                    'filter' => [
                        'owner.owner.email' => 'TestBusinessUnit1@local.com'
                    ],
                    'fields' => [
                        'users' => 'username'
                    ],
                    'page'   => [
                        'size' => 3
                    ]
                ],
                'expects'      => 'output_filters_3.yml',
                'replacements' => 'replaceUserIdsInExpectation',
                'identifier'   => 'username'
            ],
            'filter by field of related entity with no result'                                             => [
                'className'  => TestEmployee::class,
                'statusCode' => 200,
                'params'     => [
                    'filter' => [
                        'department' => 999999999999999
                    ],
                    'page'   => [
                        'size' => 3
                    ]
                ],
                'expects'    => ['data' => []],
            ],
            'filter by wrong field name'                                                                   => [
                'className'  => TestEmployee::class,
                'statusCode' => 400,
                'params'     => [
                    'filter' => [
                        'wrongFieldName' => 'value'
                    ],
                    'page'   => [
                        'size' => 3
                    ]
                ],
                'expects'    => [
                    'errors' => [
                        [
                            'status' => '400',
                            'title'  => 'filter constraint',
                            'detail' => 'The filter is not supported.',
                            'source' => [
                                'parameter' => 'filter[wrongFieldName]'
                            ]
                        ]
                    ]
                ],
            ],
            'filter by field of related entity (employee.department)'                                      => [
                'className'    => TestEmployee::class,
                'statusCode'   => 200,
                'params'       => [
                    'filter' => [
                        'department.name' => 'TestDepartment1'
                    ],
                    'page'   => [
                        'size' => 3
                    ]
                ],
                'expects'      => 'output_filters_1.yml',
                'replacements' => 'replaceTestEmployeeIdsInExpectation',
                'identifier'   => 'name'
            ],
        ];
    }

    /**
     * @param array  $expectation
     * @param string $identifier
     *
     * @return array
     */
    protected function replaceTestEmployeeIdsInExpectation(array $expectation, $identifier)
    {
        foreach ($expectation['data'] as $index => $data) {
            $referenceName = $data['attributes'][$identifier];

            /** @var TestEmployee $referenceObject */
            $referenceObject = $this->getReference($referenceName);
            $expectation['data'][$index]['id'] = (string) $referenceObject->getId();
            $expectation['data'][$index]['relationships']['department']['data']['id']
                = (string) $referenceObject->getDepartment()->getId();
        }

        return $expectation;
    }

    /**
     * @param array  $expectation
     * @param string $identifier
     *
     * @return array
     */
    protected function replaceUserIdsInExpectation(array $expectation, $identifier)
    {
        foreach ($expectation['data'] as $index => $data) {
            $referenceName = $data['attributes'][$identifier];

            /** @var User $referenceObject */
            $referenceObject = $this->getReference($referenceName);
            $expectation['data'][$index]['id'] = (string) $referenceObject->getId();
        }

        return $expectation;
    }
}
