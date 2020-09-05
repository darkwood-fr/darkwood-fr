<?php

namespace App\Form\Admin;

use App\Entity\Contact;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 *  Form Type.
 */
class ContactType extends AbstractType
{
    /**
     * Build Form.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $locale = $options['locale'];

        $builder->add('email', TextType::class, array('required' => false));
        $builder->add('website', TextType::class, array('required' => false));
        $builder->add('content', TextareaType::class, array('required' => false));
        /*$builder->add('user', EntityType::class, array(
            'class' => 'UserBundle:User',
            'placeholder' => '- Choisissez un utilisateur -',
            'required' => false,
        ));*/
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Contact::class,
            'locale' => null,
        ));
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'contact';
    }
}
