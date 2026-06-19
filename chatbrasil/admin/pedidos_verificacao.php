<?php 
// admin/pedidos_verificacao.php 
$pedidosVerificacao = $pedidosVerificacao ?? [];
?>
<div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4 md:col-span-2">
    <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
        <div class="p-1.5 bg-sky-50 text-sky-600 rounded-lg border border-sky-100">
            <i data-lucide="award" class="w-4 h-4"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pedidos de Verificado Pendentes</h3>
    </div>
    
    <p class="text-[11px] text-slate-400 leading-relaxed">
        Fila de triagem para análise de usuários que enviaram requisição manual reivindicando o selo azul de autenticidade.
    </p>
    
    <div class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
        <?php if(empty($pedidosVerificacao)): ?>
            <div class="text-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200 text-slate-400 font-medium text-[11px]">
                Nenhuma solicitação pendente na fila operacional.
            </div>
        <?php else: ?>
            <?php foreach($pedidosVerificacao as $pedido): ?>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                            <?php echo strtoupper(substr($pedido['username'], 0, 1)); ?>
                        </div>
                        <div class="truncate">
                            <span class="block text-xs font-bold text-slate-800 truncate">
                                <?php echo htmlspecialchars($pedido['username']); ?>
                            </span>
                            <?php if(!empty($pedido['motivo_solicitacao'])): ?>
                                <span class="block text-[10px] text-slate-500 italic truncate">
                                    "<?php echo htmlspecialchars($pedido['motivo_solicitacao']); ?>"
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <form method="POST" class="inline m-0">
                            <input type="hidden" name="user_id" value="<?php echo $pedido['user_id']; ?>">
                            <input type="hidden" name="action" value="aprovar_verificado">
                            <button type="submit" title="Conceder Selo" class="p-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm active:scale-95 custom-transition">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>

                        <form method="POST" class="inline m-0" onsubmit="return confirm('Deseja rejeitar este pedido?');">
                            <input type="hidden" name="user_id" value="<?php echo $pedido['user_id']; ?>">
                            <input type="hidden" name="action" value="recusar_verificado">
                            <button type="submit" title="Recusar Requisito" class="p-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white shadow-sm active:scale-95 custom-transition">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>