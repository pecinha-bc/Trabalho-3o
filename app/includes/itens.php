<?php
function mbNomeItem(array $item): string {
    $nome = (string) ($item['nome'] ?? 'Item');
    $mapa = [
        'Pocao' => 'Poção',
        'Super Pocao' => 'Super Poção',
        'Hiper Pocao' => 'Hiper Poção',
        'Pocao Maxima' => 'Poção Máxima',
        'Restauracao Total' => 'Restauração Total',
        'Reviver' => 'Reviver',
        'Antidoto' => 'Antídoto',
        'Burn Heal' => 'Antiqueimadura',
        'Ice Heal' => 'Descongelante',
        'Awakening' => 'Despertador',
        'Paralyze Heal' => 'Antiparalisia',
        'Full Heal' => 'Cura Total',
        'Item de Evolucao' => 'Item de Evolução'
    ];
    return $mapa[$nome] ?? $nome;
}
function mbIconeItem(array $item): string {
    $tipo = (string) ($item['tipo_efeito'] ?? '');
    $status = (string) ($item['status_removido'] ?? '');
    $valor = (int) ($item['valor_efeito'] ?? 0);
    if ($tipo === 'evolucao') return '../assets/imagens/item-evolucao.svg';
    if ($tipo === 'reviver') return '../assets/imagens/item-reviver.svg';
    if ($tipo === 'restauracao_total') return '../assets/imagens/item-restauracao.svg';
    if ($tipo === 'cura_total') return '../assets/imagens/item-pocao-maxima.svg';
    if ($tipo === 'cura_status') {
        $mapa = [
            'queimadura' => 'item-antiqueimadura.svg',
            'congelamento' => 'item-descongelante.svg',
            'veneno' => 'item-antidoto.svg',
            'sono' => 'item-despertador.svg',
            'paralisia' => 'item-antiparalisia.svg',
            'todos' => 'item-cura-total.svg'
        ];
        return '../assets/imagens/' . ($mapa[$status] ?? 'item-cura-total.svg');
    }
    if ($tipo === 'cura_hp') {
        if ($valor >= 100) return '../assets/imagens/item-pocao-maxima.svg';
        if ($valor >= 60) return '../assets/imagens/item-hiperpocao.svg';
        if ($valor >= 40) return '../assets/imagens/item-superpocao.svg';
        return '../assets/imagens/item-pocao.svg';
    }
    return '../assets/imagens/item-pocao.svg';
}
