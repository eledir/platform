<?php

namespace Oro\Bundle\SegmentBundle\Filter;

use Doctrine\ORM\Query\Parameter;

use Symfony\Component\Form\FormFactoryInterface;

use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Oro\Bundle\FilterBundle\Filter\EntityFilter;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\SegmentBundle\Query\DynamicSegmentQueryBuilder;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;

class SegmentFilter extends EntityFilter
{
    /** @var DynamicSegmentQueryBuilder */
    protected $dynamicSegmentQueryBuilder;

    /**
     * Constructor
     *
     * @param FormFactoryInterface       $factory
     * @param FilterUtility              $util
     * @param DynamicSegmentQueryBuilder $dynamicSegmentQueryBuilder
     */
    public function __construct(
        FormFactoryInterface $factory,
        FilterUtility $util,
        DynamicSegmentQueryBuilder $dynamicSegmentQueryBuilder
    ) {
        parent::__construct($factory, $util);
        $this->dynamicSegmentQueryBuilder = $dynamicSegmentQueryBuilder;
    }

    /**
     * {@inheritDoc}
     */
    public function getForm()
    {
        if (!$this->form) {
            // hard coded field, do not allow to pass any option
            $this->form = $this->formFactory->create(
                $this->getFormType(),
                [],
                [
                    'csrf_protection' => false,
                    'field_options'   => [
                        'class'    => 'OroSegmentBundle:Segment',
                        'property' => 'name',
                        'required' => true,
                    ]
                ]
            );
        }

        return $this->form;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        if (!(isset($data['value']) && $data['value'] instanceof Segment)) {
            return false;
        }

        /** @var Segment $segment */
        $segment = $data['value'];
        if ($segment->getType()->getName() === SegmentType::TYPE_DYNAMIC) {
            $query = $this->dynamicSegmentQueryBuilder->build($segment);
        } else {
            // @TODO process static here
        }
        $field = $this->get(FilterUtility::DATA_NAME_KEY);
        $expr  = $ds->expr()->in($field, $query->getDQL());
        $this->applyFilterToClause($ds, $expr);

        $params = $query->getParameters();
        /** @var Parameter $param */
        foreach ($params as $param) {
            $ds->setParameter($param->getName(), $param->getValue(), $param->getType());
        }
    }
}
