<?php

namespace Matthias\SymfonyConsoleForm\Helper;

use Matthias\SymfonyConsoleForm\Transformer\ChoiceTransformer;
use Matthias\SymfonyConsoleForm\Transformer\FormToQuestionTransformer;
use Matthias\SymfonyConsoleForm\Transformer\PasswordTransformer;
use Matthias\SymfonyConsoleForm\Transformer\TextTransformer;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;

class FormQuestionHelper extends Helper
{
    private $formFactory;
    /** @var FormToQuestionTransformer[] */
    private $transformers = array();

    public function getName()
    {
        return 'form_question';
    }

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    public function interactUsingForm($formType, InputInterface $input, OutputInterface $output)
    {
        $form = $this->createForm($formType, $input->getOptions());
        $view = $form->createView();
        $submittedData = [];

        foreach ($form as $name => $field) {
            /** @var Form $field */

            $fieldView = $view[$name];
            /** @var FormView $fieldView */

            $fieldType = $field->getConfig()->getType()->getName();

            $question = $this->getTransformerFor($fieldType)->transform($field, $fieldView);

            $value = $this->questionHelper()->ask($input, $output, $question);

            $submittedData[$name] = $value;
        }

        $form->submit($submittedData);
        if ($form->isValid()) {
            foreach ($form as $field) {
                $input->setOption($field->getName(), $field->getData());
            }
        } else {
            throw new \RuntimeException(sprintf('Invalid data provided: %s', $form->getErrors(true, false)));
        }

        return $form->getData();
    }

    public function inputDefinition($formType)
    {
        // TODO cache this
        $form = $this->createForm($formType);

        $inputDefinition = new InputDefinition();

        foreach ($form as $name => $field) {
            $required = $field->getConfig()->getOption('required', false);
            if ($required) {
                $type = InputOption::VALUE_REQUIRED;
                $default = null;
            } else {
                $type = InputOption::VALUE_OPTIONAL;
                $default = $field->getConfig()->getOption('data', null);
            }

            $inputDefinition->addOption(new InputOption($name, null, $type, null, $default));
        }

        return $inputDefinition;
    }

    private function createForm($type)
    {
        // TODO support data from options using a ViewTransformer
        return $this->formFactory->create($type);
    }

    public function addTransformer($formType, FormToQuestionTransformer $transformer)
    {
        $this->transformers[$formType] = $transformer;
    }

    private function getTransformerFor($fieldType)
    {
        if (!isset($this->transformers[$fieldType])) {
            throw new \InvalidArgumentException("No transformer exists for field type '$fieldType'");
        }

        return $this->transformers[$fieldType];
    }

    /**
     * @return QuestionHelper
     */
    private function questionHelper()
    {
        return $this->getHelperSet()->get('question');
    }
}
