<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\Form\Type\EuroAmountType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

final class EuroAmountTypeTest extends TestCase
{
    private FormFactoryInterface $factory;
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactory();
        $this->previousLocale = \Locale::getDefault();
        \Locale::setDefault('fr');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function frenchInputs(): iterable
    {
        yield 'entier' => ['650', 65000];
        yield 'virgule décimale' => ['650,50', 65050];
        yield 'point décimal' => ['650.50', 65050];
        // Sans l'option « grouping », Symfony refuse ces trois saisies
        yield 'espace entre les milliers' => ['1 234,56', 123456];
        yield 'espace insécable' => ["1\u{00A0}234,56", 123456];
        yield 'espace fine insécable' => ["1\u{202F}234,56", 123456];
        // 19,99 × 100 vaut 1998,9999… en virgule flottante : le résultat doit quand même être 1999
        yield 'arrondi des centimes' => ['19,99', 1999];
    }

    #[DataProvider('frenchInputs')]
    public function testFrenchInputIsStoredInCents(string $input, int $expectedCents): void
    {
        $form = $this->factory->create(EuroAmountType::class);
        $form->submit($input);

        self::assertTrue($form->isSynchronized(), \sprintf('« %s » devrait être accepté.', $input));
        self::assertSame($expectedCents, $form->getData());
    }

    public function testTextIsRejected(): void
    {
        $form = $this->factory->create(EuroAmountType::class);
        $form->submit('six cent cinquante');

        self::assertFalse($form->isSynchronized());
    }
}
