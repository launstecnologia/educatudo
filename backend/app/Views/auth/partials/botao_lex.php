<?php $lex_perfil = (string) ($lex_perfil ?? ''); ?>
<a href="<?= URL ?>/lex/entrar?perfil=<?= urlencode($lex_perfil) ?>"
   style="display:flex;width:100%;margin-top:1rem;overflow:hidden;border-radius:0.5rem;border:1px solid #cbd5e1;background:#e2e8f0;color:#334155;text-decoration:none;">
    <span style="display:flex;align-items:center;justify-content:center;padding:0.55rem 0.9rem;border-right:1px solid #cbd5e1;background:#f1f5f9;">
        <img src="<?= URL ?>/static/images/logo-lex.png" alt="LEX" style="height:22px;width:auto;display:block;">
    </span>
    <span style="display:flex;flex:1;align-items:center;justify-content:center;padding:0.75rem 1rem;font-size:0.95rem;font-weight:500;">Entrar com a LEX</span>
</a>
