<?php
namespace Vivo\CMS\UI\Manager\Form;

use Vivo\CMS\UI\Manager\Form\Fieldset\EntityEditor as EntityEditorFieldset;
use Vivo\Form\Form;
use Zend\Stdlib\Hydrator\ClassMethods as ClassMethodsHydrator;

/**
 * ContentEditor form.
 */
class ContentEditor extends Form
{
    /**
     * Constructor.
     *
     * @param string $name Form and fieldset name.
     * @param array $contents
     * @param array $metadata
     */
    public function __construct($name, array $contents, array $metadata)
    {
        parent::__construct($name, false);

        $this->setWrapElements(true);
        $this->setAttribute('method', 'post');

        $options = array();
        $options[] = 'NEW';

        foreach ($contents as $k => $content) { /* @var $content \Vivo\CMS\Model\Content */
            $options[$k] = sprintf('1.%d {%s} %s', $k, $content->getUuid(), get_class($content));
        }

        // Version selecbox
        $this->add(array(
            'name' => 'version',
            'type' => 'Vivo\Form\Element\Select',
            'attributes' => array('options' => $options),
        ));

        // Fieldset
        $fieldset = new EntityEditorFieldset('content', $metadata);
        $fieldset->setHydrator(new ClassMethodsHydrator(false));
        $fieldset->setOptions(array('use_as_base_fieldset' => true));

        $this->add($fieldset);
    }

}