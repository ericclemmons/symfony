<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeCollectionListener;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntitiesToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntityToIdTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class EntityType extends AbstractType
{
    protected $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('group_by', $options['group_by']);

        if ($options['multiple']) {
            $builder
                ->addEventSubscriber(new MergeCollectionListener())
                ->prependClientTransformer(new EntitiesToArrayTransformer($options['choice_list']))
            ;
        } else {
            $builder->prependClientTransformer(new EntityToIdTransformer($options['choice_list']));
        }
    }

    public function buildView(FormView $view, FormInterface $form)
    {
        if (null !== $form->getAttribute('group_by')) {
            $groupBy    = $form->getAttribute('group_by');
            $list       = $form->getAttribute('choice_list');
            $flattened  = $view->get('choices');
            $nested     = array();

            foreach ($flattened as $key => $label) {
                $entity = $list->getEntity($key);

                if ($groupBy instanceof \Closure) {
                    $group = $groupBy($entity);
                } else {
                    try {
                        $path   = new PropertyPath($groupBy);
                        $group  = (String) $path->getValue($entity);
                    } catch (UnexpectedTypeException $e) {
                        // PropertyPath cannot traverse entity
                    }
                }

                if (empty($group)) {
                    $nested[$key] = $label;
                } else {
                    $nested[$group][$key] = $label;
                }
            }

            $view->set('choices', $nested);
        }
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'em'                => null,
            'class'             => null,
            'property'          => null,
            'query_builder'     => null,
            'choices'           => array(),
            'group_by'          => null,
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list'])) {
            $defaultOptions['choice_list'] = new EntityChoiceList(
                $this->registry->getManager($options['em']),
                $options['class'],
                $options['property'],
                $options['query_builder'],
                $options['choices']
            );
        }

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return 'choice';
    }

    public function getName()
    {
        return 'entity';
    }
}
