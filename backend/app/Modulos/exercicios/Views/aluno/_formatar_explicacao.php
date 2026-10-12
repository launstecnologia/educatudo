<?php
if (!function_exists('formatarExplicacaoExercicioPersonalizado')) {
    function formatarExplicacaoExercicioPersonalizado(?string $texto): string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '<p class="text-sm text-gray-600">Sem explicação disponível.</p>';
        }

        $safe = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $texto), ENT_QUOTES, 'UTF-8');
        $safe = nl2br($safe);

        $safe = preg_replace(
            '/\*\*Alternativa\s+([A-E])\)\*\*/i',
            '<span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold text-xs mr-2">Alternativa $1</span>',
            $safe
        );
        $safe = preg_replace(
            '/\*\*(CORRETA|INCORRETA)\*\*/i',
            '<span class="font-semibold text-slate-900">$1</span>',
            $safe
        );
        $safe = preg_replace(
            '/\*\*Gabarito:\s*([A-E])\*\*/i',
            '<span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-semibold text-xs">Gabarito: $1</span>',
            $safe
        );
        $safe = str_replace('**', '', $safe);

        $blocos = preg_split('/(?:<br\s*\/?>\s*){2,}/i', $safe);
        $blocos = array_values(array_filter(array_map('trim', $blocos), static function ($item) {
            return $item !== '';
        }));

        if (empty($blocos)) {
            return '<p class="text-sm text-gray-700 leading-relaxed">' . $safe . '</p>';
        }

        $html = '';
        foreach ($blocos as $bloco) {
            $classe = (stripos($bloco, 'Gabarito:') !== false)
                ? 'text-sm pt-2 border-t border-emerald-200'
                : 'text-sm text-gray-700 leading-relaxed';
            $html .= '<p class="' . $classe . '">' . $bloco . '</p>';
        }

        return $html;
    }
}
