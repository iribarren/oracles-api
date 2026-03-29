<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\OracleOption;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class OracleOptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OracleOption::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setDisabled();
        yield AssociationField::new('category');
        yield TextField::new('value');
        yield TextField::new('hint')->setRequired(false);
        yield IntegerField::new('display_order', 'Display Order');
        yield BooleanField::new('is_active', 'Active');
    }

    public function configureFilters(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters $filters): \EasyCorp\Bundle\EasyAdminBundle\Config\Filters
    {
        return $filters
            ->add(EntityFilter::new('category'))
            ->add(BooleanFilter::new('is_active', 'Active'));
    }
}
