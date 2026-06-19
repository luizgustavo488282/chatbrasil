<?php 
// admin/tabela_usuarios.php 
$usuarios = $usuarios ?? [];
?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-200">
                    <th class="px-5 py-3.5 w-16">ID</th>
                    <th class="px-5 py-3.5">Nome de Usuário</th>
                    <th class="px-5 py-3.5">E-mail Cadastrado</th>
                    <th class="px-5 py-3.5">Nível de Acesso</th>
                    <th class="px-5 py-3.5 w-20 text-center">Selo</th>
                    <th class="px-5 py-3.5 text-center w-[340px]">Ações de Auditoria</th>
                </tr>
            </thead>
            <tbody class="text-slate-600 text-xs divide-y divide-slate-100">
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400 font-medium">Nenhum registro localizado na base de dados corrente.</td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $user): ?>
                        <?php 
                            $estaBanido = isset($user['is_banned']) && $user['is_banned'] == 1;
                            $cargo = !empty($user['cargo']) ? htmlspecialchars($user['cargo']) : 'Usuário';
                        ?>
                        <tr class="hover:bg-slate-50/80 custom-transition <?php echo $estaBanido ? 'bg-rose-50/40' : ''; ?>">
                            <td class="px-5 py-4 font-bold text-slate-400">#<?php echo $user['id']; ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <span class="font-bold text-slate-800 flex items-center gap-2">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if($estaBanido): ?>
                                            <span class="text-[8px] bg-rose-600 text-white font-extrabold px-1.5 py-0.5 rounded uppercase">Restrito</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-500 font-medium"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-md border uppercase tracking-wider inline-block <?php 
                                    if (strtolower($cargo) == 'admin') echo 'bg-rose-50 text-rose-700 border-rose-200';
                                    elseif (strtolower($cargo) == 'vip') echo 'bg-amber-50 text-amber-700 border-amber-200';
                                    else echo 'bg-slate-100 text-slate-600 border-slate-200';
                                ?>">
                                    <?php echo $cargo; ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if(isset($user['is_verified']) && $user['is_verified'] == 1): ?>
                                    <span class="text-sky-500 inline-block filter drop-shadow-sm"><i data-lucide="badge-check" class="w-4 h-4 fill-sky-100"></i></span>
                                <?php else: ?>
                                    <span class="text-slate-300 inline-block"><i data-lucide="badge-check" class="w-4 h-4"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_cargo">
                                        <select name="novo_cargo" onchange="this.form.submit()" class="text-[11px] font-bold bg-slate-50 text-slate-600 border border-slate-200 rounded-lg px-2 py-1 focus:outline-none custom-transition cursor-pointer">
                                            <option value="Usuário" <?php if(strtolower($cargo) == 'usuário' || strtolower($cargo) == 'usuario') echo 'selected'; ?>>Usuário</option>
                                            <option value="VIP" <?php if(strtolower($cargo) == 'vip') echo 'selected'; ?>>VIP</option>
                                            <option value="Admin" <?php if(strtolower($cargo) == 'admin') echo 'selected'; ?>>Admin</option>
                                        </select>
                                    </form>

                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_verify">
                                        <button type="submit" title="Mudar Verificação" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-sky-600 hover:bg-sky-50 custom-transition">
                                            <i data-lucide="award" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline-flex items-center gap-1 m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if($estaBanido): ?>
                                            <input type="hidden" name="action" value="revogar_ban">
                                            <button type="submit" title="Revogar Restrição" class="p-1.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 custom-transition">
                                                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="toggle_ban">
                                            <select name="tempo_banimiento" class="text-[10px] bg-slate-50 border border-slate-200 rounded px-1 py-0.5 font-semibold text-slate-500">
                                                <option value="perma">Perma</option>
                                                <option value="7">7d</option>
                                                <option value="30">30d</option>
                                            </select>
                                            <button type="submit" title="Aplicar Suspensão" class="p-1.5 rounded-lg border border-slate-200 text-amber-500 hover:bg-amber-50 custom-transition">
                                                <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="POST" class="inline m-0" onsubmit="return confirm('Deseja deletar permanentemente esta conta?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" title="Excluir Definitivo" class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 hover:bg-rose-50 custom-transition">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>