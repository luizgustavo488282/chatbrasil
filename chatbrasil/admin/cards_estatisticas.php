<?php 
// admin/cards_estatisticas.php
$totalUsuarios = $totalUsuarios ?? 0;
$verificados   = $verificados ?? 0;
$admins        = $admins ?? 0;
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div class="space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Base de Usuários</span>
            <h3 class="text-2xl font-black text-slate-800"><?php echo $totalUsuarios; ?></h3>
        </div>
        <div class="p-2.5 bg-slate-50 text-slate-500 rounded-xl border border-slate-100"><i data-lucide="users" class="w-4 h-4"></i></div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div class="space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Contas Autenticadas</span>
            <h3 class="text-2xl font-black text-slate-800"><?php echo $verificados; ?></h3>
        </div>
        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl border border-blue-100/40"><i data-lucide="badge-check" class="w-4 h-4"></i></div>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
        <div class="space-y-0.5">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Corpo Administrativo</span>
            <h3 class="text-2xl font-black text-slate-800"><?php echo $admins; ?></h3>
        </div>
        <div class="p-2.5 bg-rose-50 text-rose-600 rounded-xl border border-rose-100/40"><i data-lucide="shield" class="w-4 h-4"></i></div>
    </div>
</div>