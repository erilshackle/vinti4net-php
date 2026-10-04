<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

use Erilshk\Sisp\Exceptions\Vinti4Exception;

/** Códigos de entidade para pagamentos de serviço e recargas. */
final class Entity
{
    /** Recarga Alou. */
    public const RECHARGE_ALOU = 2;

    /** Recarga Unitel T+. */
    public const RECHARGE_UNITEL = 16;

    /** Recarga Alou ETU. */
    public const RECHARGE_ALOU_ETU = 29;

    /** Recarga Alou Club. */
    public const RECHARGE_ALOU_CLUB = 30;

    /** Recarga Electra Sul. */
    public const RECHARGE_ELECTRA_SOUTH = 88;

    /** Recarga Electra Norte. */
    public const RECHARGE_ELECTRA_NORTH = 87;

    /** Pagamento Alou fixo. */
    public const SERVICE_ALOU_LANDLINE = 4;

    /** Pagamento Alou móvel. */
    public const SERVICE_ALOU_MOBILE = 5;

    /** Pagamento Alou Multimédia. */
    public const SERVICE_ALOU_MULTIMEDIA = 6;

    /** Pagamento Garantia Seguros. */
    public const SERVICE_GARANTIA = 7;

    /** Pagamento Impar Seguros. */
    public const SERVICE_IMPAR = 8;

    /** Pagamento Unitel TMais. */
    public const SERVICE_UNITEL_TMAIS = 46;

    /** Pagamento Águas de Santiago. */
    public const SERVICE_AGUAS_SANTIAGO = 47;

    /** Pagamento Águas de Santo Antão. */
    public const SERVICE_AGUAS_SANTO_ANTAO = 131;

    /** Pagamento Águas de São Nicolau. */
    public const SERVICE_AGUAS_SAO_NICOLAU = 140;

    /** Pagamento Águas e Energia da Boa Vista. */
    public const SERVICE_AGUAS_ENERGIA_BOA_VISTA = 92;

    /** Pagamento Águas e Energia do Maio. */
    public const SERVICE_AGUAS_ENERGIA_MAIO = 146;

    /** Pagamento Água Brava. */
    public const SERVICE_AGUA_BRAVA = 139;

    /** Pagamento Sol Atlântico. */
    public const SERVICE_SOL_ATLANTICO = 48;

    /** Pagamento Electra SA. */
    public const SERVICE_ELECTRA = 135;

    /** Pagamento EDEC. */
    public const SERVICE_EDEC = 136;

    /** @var array<string, list<int>> */
    private const CATEGORIES = [
        'recharge' => [self::RECHARGE_ALOU, self::RECHARGE_UNITEL, self::RECHARGE_ALOU_ETU, self::RECHARGE_ALOU_CLUB, self::RECHARGE_ELECTRA_SOUTH, self::RECHARGE_ELECTRA_NORTH],
        'electricity' => [self::RECHARGE_ELECTRA_SOUTH, self::RECHARGE_ELECTRA_NORTH, self::SERVICE_AGUAS_ENERGIA_BOA_VISTA, self::SERVICE_AGUAS_ENERGIA_MAIO, self::SERVICE_ELECTRA, self::SERVICE_EDEC],
        'water' => [self::SERVICE_AGUAS_SANTIAGO, self::SERVICE_AGUAS_SANTO_ANTAO, self::SERVICE_AGUAS_SAO_NICOLAU, self::SERVICE_AGUAS_ENERGIA_BOA_VISTA, self::SERVICE_AGUAS_ENERGIA_MAIO, self::SERVICE_AGUA_BRAVA],
        'insurance' => [self::SERVICE_GARANTIA, self::SERVICE_IMPAR],
        'internet' => [self::SERVICE_ALOU_MULTIMEDIA],
        'transport' => [self::SERVICE_SOL_ATLANTICO],
        'telephone' => [self::SERVICE_ALOU_LANDLINE, self::SERVICE_ALOU_MOBILE, self::SERVICE_UNITEL_TMAIS],
    ];

    /**
     * Lista as constantes como nome => código, opcionalmente por categoria.
     *
     * @param 'recharge'|'electricity'|'water'|'insurance'|'internet'|'transport'|'telephone'|null $category
     * @return array<string, int>
     * @throws Vinti4Exception Se a categoria não existir.
     */
    public static function all(?string $category = null): array
    {
        /** @var array<string, int> $entities */
        $entities = (new \ReflectionClass(self::class))->getConstants(
            \ReflectionClassConstant::IS_PUBLIC,
        );

        if ($category === null) {
            return $entities;
        }

        $category = strtolower(trim($category));
        if (!isset(self::CATEGORIES[$category])) {
            throw new Vinti4Exception("Categoria de entidade inválida: {$category}.");
        }

        return array_filter(
            $entities,
            static fn(int $code): bool => in_array($code, self::CATEGORIES[$category], true),
        );
    }

    private function __construct() {}
}
