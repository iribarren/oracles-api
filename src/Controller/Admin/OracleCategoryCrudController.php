<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\OracleCategory;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OracleCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OracleCategory::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex()->setDisabled();
        yield TextField::new('name');
        yield IntegerField::new('display_order', 'Display Order');
        yield AssociationField::new('options')->onlyOnIndex();
    }
}
