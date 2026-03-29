<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GameSession;
use App\Enum\GamePhase;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GameSessionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GameSession::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setDisabled();
        yield TextField::new('character_name', 'Character Name');
        yield TextField::new('genre');
        yield TextField::new('epoch');
        yield ChoiceField::new('current_phase', 'Current Phase')
            ->setChoices(array_combine(
                array_map(fn (GamePhase $p) => $p->value, GamePhase::cases()),
                GamePhase::cases(),
            ));
        yield IntegerField::new('overcome_score', 'Overcome Score');
        yield DateTimeField::new('created_at', 'Created At')->onlyOnIndex();
        yield DateTimeField::new('updated_at', 'Updated At')->onlyOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Read-only: disable create and edit, keep only delete and detail
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
