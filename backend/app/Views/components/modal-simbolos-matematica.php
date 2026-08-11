<!-- Modal de Símbolos Matemáticos, Equações e Físicos -->
<div id="modalSimbolos" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" style="z-index: 9999;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 flex justify-between items-center">
                <h2 class="text-2xl font-bold">Símbolos e Equações</h2>
                <button onclick="fecharModalSimbolos()" class="text-white hover:text-gray-200 text-2xl font-bold">
                    ×
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="border-b border-gray-200 flex flex-wrap">
                <button onclick="mostrarTabSimbolos('equacoes')" id="tab-equacoes" 
                        class="tab-simbolos active px-4 py-3 font-medium text-blue-600 border-b-2 border-blue-600">
                    ∑ Equações
                </button>
                <button onclick="mostrarTabSimbolos('matematica')" id="tab-matematica" 
                        class="tab-simbolos px-4 py-3 font-medium text-gray-600 hover:text-blue-600">
                    📐 Símbolos
                </button>
                <button onclick="mostrarTabSimbolos('fisica')" id="tab-fisica" 
                        class="tab-simbolos px-4 py-3 font-medium text-gray-600 hover:text-blue-600">
                    ⚛️ Física
                </button>
                <button onclick="mostrarTabSimbolos('quimica')" id="tab-quimica" 
                        class="tab-simbolos px-4 py-3 font-medium text-gray-600 hover:text-blue-600">
                    🧪 Química
                </button>
            </div>
            
            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Tab Equações (LaTeX) -->
                <div id="conteudo-equacoes" class="tab-conteudo">
                    <p class="text-sm text-gray-600 mb-4">Fórmulas em LaTeX serão renderizadas para o aluno. Use os atalhos abaixo ou digite LaTeX no campo e clique em Inserir.</p>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Inserir LaTeX personalizado</label>
                        <div class="flex gap-2 flex-wrap">
                            <input type="text" id="input-latex-custom" placeholder="Ex: \frac{1}{2}, x^2, \sqrt{n}" 
                                   class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <button type="button" onclick="inserirLatexCustom()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Inserir</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Frações e raízes</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( \\frac{a}{b} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Fração">\frac{a}{b}</button>
                                <button type="button" onclick="inserirSimbolo('\( \\frac{1}{2} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">\frac{1}{2}</button>
                                <button type="button" onclick="inserirSimbolo('\( \\sqrt{x} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">\sqrt{x}</button>
                                <button type="button" onclick="inserirSimbolo('\( \\sqrt[n]{x} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">\sqrt[n]{x}</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Potências e índices</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( x^2 \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">x²</button>
                                <button type="button" onclick="inserirSimbolo('\( x^n \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">x^n</button>
                                <button type="button" onclick="inserirSimbolo('\( x_1 \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">x₁</button>
                                <button type="button" onclick="inserirSimbolo('\( a^{b} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">a^b</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Somatório, integral, limites</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( \\sum_{i=1}^{n} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">∑</button>
                                <button type="button" onclick="inserirSimbolo('\( \\int \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">∫</button>
                                <button type="button" onclick="inserirSimbolo('\( \\int_a^b \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">∫ₐᵇ</button>
                                <button type="button" onclick="inserirSimbolo('\( \\lim_{x \\to 0} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">lim</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Letras gregas (LaTeX)</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( \\pi \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">π</button>
                                <button type="button" onclick="inserirSimbolo('\( \\alpha \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">α</button>
                                <button type="button" onclick="inserirSimbolo('\( \\beta \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">β</button>
                                <button type="button" onclick="inserirSimbolo('\( \\theta \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">θ</button>
                                <button type="button" onclick="inserirSimbolo('\( \\Delta \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">Δ</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Relações e conjuntos numéricos (LaTeX)</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( \\neq \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Diferente">≠</button>
                                <button type="button" onclick="inserirSimbolo('\( \\not\\subset \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Não contido">⊄</button>
                                <button type="button" onclick="inserirSimbolo('\( \\mathbb{Z} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Inteiros">ℤ</button>
                                <button type="button" onclick="inserirSimbolo('\( \\mathbb{Q} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Racionais">ℚ</button>
                                <button type="button" onclick="inserirSimbolo('\( \\mathbb{C} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Complexos">ℂ</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Equações comuns</h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="inserirSimbolo('\( x = \\frac{-b \\pm \\sqrt{b^2-4ac}}{2a} \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm" title="Bhaskara">Bhaskara</button>
                                <button type="button" onclick="inserirSimbolo('\( a^2 + b^2 = c^2 \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">Pitágoras</button>
                                <button type="button" onclick="inserirSimbolo('\( \\angle \)')" class="btn-simbolo px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm">∠</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Tab Matemática (símbolos avulsos) -->
                <div id="conteudo-matematica" class="tab-conteudo hidden">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <!-- Operadores -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Operadores</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('+')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">+</button>
                                <button onclick="inserirSimbolo('−')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">−</button>
                                <button onclick="inserirSimbolo('×')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">×</button>
                                <button onclick="inserirSimbolo('÷')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">÷</button>
                                <button onclick="inserirSimbolo('±')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">±</button>
                                <button onclick="inserirSimbolo('∓')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∓</button>
                                <button onclick="inserirSimbolo('=')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">=</button>
                                <button onclick="inserirSimbolo('≠')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Diferente">≠</button>
                                <button onclick="inserirSimbolo('≈')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">≈</button>
                                <button onclick="inserirSimbolo('>')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">></button>
                                <button onclick="inserirSimbolo('<')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg"><</button>
                                <button onclick="inserirSimbolo('≥')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">≥</button>
                                <button onclick="inserirSimbolo('≤')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">≤</button>
                            </div>
                        </div>
                        
                        <!-- Frações e Potências -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Frações e Potências</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('½')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">½</button>
                                <button onclick="inserirSimbolo('⅓')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">⅓</button>
                                <button onclick="inserirSimbolo('¼')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">¼</button>
                                <button onclick="inserirSimbolo('⅔')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">⅔</button>
                                <button onclick="inserirSimbolo('¾')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">¾</button>
                                <button onclick="inserirSimbolo('⅛')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">⅛</button>
                                <button onclick="inserirSimbolo('²')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">²</button>
                                <button onclick="inserirSimbolo('³')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">³</button>
                                <button onclick="inserirSimbolo('ⁿ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">ⁿ</button>
                            </div>
                        </div>
                        
                        <!-- Letras Gregas -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Letras Gregas</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('α')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">α</button>
                                <button onclick="inserirSimbolo('β')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">β</button>
                                <button onclick="inserirSimbolo('γ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">γ</button>
                                <button onclick="inserirSimbolo('δ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">δ</button>
                                <button onclick="inserirSimbolo('ε')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">ε</button>
                                <button onclick="inserirSimbolo('θ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">θ</button>
                                <button onclick="inserirSimbolo('λ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">λ</button>
                                <button onclick="inserirSimbolo('μ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">μ</button>
                                <button onclick="inserirSimbolo('π')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">π</button>
                                <button onclick="inserirSimbolo('ρ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">ρ</button>
                                <button onclick="inserirSimbolo('σ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">σ</button>
                                <button onclick="inserirSimbolo('τ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">τ</button>
                                <button onclick="inserirSimbolo('φ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">φ</button>
                                <button onclick="inserirSimbolo('ω')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">ω</button>
                                <button onclick="inserirSimbolo('Ω')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">Ω</button>
                                <button onclick="inserirSimbolo('Δ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">Δ</button>
                                <button onclick="inserirSimbolo('Σ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">Σ</button>
                            </div>
                        </div>
                        
                        <!-- Geometria -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Geometria</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('∠')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∠</button>
                                <button onclick="inserirSimbolo('°')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">°</button>
                                <button onclick="inserirSimbolo('⊥')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">⊥</button>
                                <button onclick="inserirSimbolo('∥')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∥</button>
                                <button onclick="inserirSimbolo('△')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">△</button>
                                <button onclick="inserirSimbolo('□')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">□</button>
                                <button onclick="inserirSimbolo('○')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">○</button>
                                <button onclick="inserirSimbolo('∞')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∞</button>
                            </div>
                        </div>
                        
                        <!-- Conjuntos -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Conjuntos</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('∈')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∈</button>
                                <button onclick="inserirSimbolo('∉')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∉</button>
                                <button onclick="inserirSimbolo('⊂')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Contido">⊂</button>
                                <button onclick="inserirSimbolo('⊄')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Não contido">⊄</button>
                                <button onclick="inserirSimbolo('⊃')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">⊃</button>
                                <button onclick="inserirSimbolo('∪')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∪</button>
                                <button onclick="inserirSimbolo('∩')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∩</button>
                                <button onclick="inserirSimbolo('∅')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∅</button>
                                <button onclick="inserirSimbolo('ℕ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Naturais">ℕ</button>
                                <button onclick="inserirSimbolo('ℤ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Inteiros">ℤ</button>
                                <button onclick="inserirSimbolo('ℚ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Racionais">ℚ</button>
                                <button onclick="inserirSimbolo('ℝ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Reais">ℝ</button>
                                <button onclick="inserirSimbolo('ℂ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg" title="Complexos">ℂ</button>
                            </div>
                        </div>
                        
                        <!-- Outros -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Outros</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('√')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">√</button>
                                <button onclick="inserirSimbolo('∛')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∛</button>
                                <button onclick="inserirSimbolo('∑')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∑</button>
                                <button onclick="inserirSimbolo('∏')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∏</button>
                                <button onclick="inserirSimbolo('∫')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∫</button>
                                <button onclick="inserirSimbolo('∂')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∂</button>
                                <button onclick="inserirSimbolo('∇')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∇</button>
                                <button onclick="inserirSimbolo('∝')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∝</button>
                                <button onclick="inserirSimbolo('∴')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∴</button>
                                <button onclick="inserirSimbolo('∵')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-blue-100 rounded text-lg">∵</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Física -->
                <div id="conteudo-fisica" class="tab-conteudo hidden">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <!-- Unidades -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Unidades</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('m')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">m</button>
                                <button onclick="inserirSimbolo('kg')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">kg</button>
                                <button onclick="inserirSimbolo('s')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">s</button>
                                <button onclick="inserirSimbolo('A')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">A</button>
                                <button onclick="inserirSimbolo('K')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">K</button>
                                <button onclick="inserirSimbolo('mol')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">mol</button>
                                <button onclick="inserirSimbolo('cd')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">cd</button>
                                <button onclick="inserirSimbolo('Hz')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">Hz</button>
                                <button onclick="inserirSimbolo('N')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">N</button>
                                <button onclick="inserirSimbolo('J')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">J</button>
                                <button onclick="inserirSimbolo('W')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">W</button>
                                <button onclick="inserirSimbolo('V')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">V</button>
                                <button onclick="inserirSimbolo('Ω')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">Ω</button>
                                <button onclick="inserirSimbolo('C')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">C</button>
                                <button onclick="inserirSimbolo('T')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">T</button>
                            </div>
                        </div>
                        
                        <!-- Grandezas Físicas -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Grandezas</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                <button onclick="inserirSimbolo('v')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="velocidade">v <span class="block text-[10px] opacity-80">velocidade</span></button>
                                <button onclick="inserirSimbolo('a')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="aceleração">a <span class="block text-[10px] opacity-80">aceleração</span></button>
                                <button onclick="inserirSimbolo('F')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="força">F <span class="block text-[10px] opacity-80">força</span></button>
                                <button onclick="inserirSimbolo('E')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="energia">E <span class="block text-[10px] opacity-80">energia</span></button>
                                <button onclick="inserirSimbolo('P')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="potência">P <span class="block text-[10px] opacity-80">potência</span></button>
                                <button onclick="inserirSimbolo('I')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="corrente">I <span class="block text-[10px] opacity-80">corrente</span></button>
                                <button onclick="inserirSimbolo('R')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="resistência">R <span class="block text-[10px] opacity-80">resistência</span></button>
                                <button onclick="inserirSimbolo('Q')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="carga">Q <span class="block text-[10px] opacity-80">carga</span></button>
                                <button onclick="inserirSimbolo('ρ')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="densidade">ρ <span class="block text-[10px] opacity-80">densidade</span></button>
                                <button onclick="inserirSimbolo('λ')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="comprimento de onda">λ <span class="block text-[10px] opacity-80">comp. onda</span></button>
                                <button onclick="inserirSimbolo('f')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-purple-100 rounded text-xs" title="frequência">f <span class="block text-[10px] opacity-80">frequência</span></button>
                            </div>
                        </div>
                        
                        <!-- Vetores -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Vetores</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('→')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">→</button>
                                <button onclick="inserirSimbolo('↑')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">↑</button>
                                <button onclick="inserirSimbolo('↓')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">↓</button>
                                <button onclick="inserirSimbolo('←')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">←</button>
                                <button onclick="inserirSimbolo('↔')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">↔</button>
                                <button onclick="inserirSimbolo('↕')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">↕</button>
                            </div>
                        </div>
                        
                        <!-- Símbolos Especiais -->
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Especiais</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <button onclick="inserirSimbolo('°C')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">°C</button>
                                <button onclick="inserirSimbolo('°F')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">°F</button>
                                <button onclick="inserirSimbolo('K')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-sm">K</button>
                                <button onclick="inserirSimbolo('Δ')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">Δ</button>
                                <button onclick="inserirSimbolo('∇')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">∇</button>
                                <button onclick="inserirSimbolo('∂')" class="btn-simbolo px-3 py-2 bg-gray-100 hover:bg-purple-100 rounded text-lg">∂</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Química -->
                <div id="conteudo-quimica" class="tab-conteudo hidden">
                    <div class="space-y-6">
                        <!-- Reações e setas -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Reações e setas</h3>
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                                <button onclick="inserirSimbolo('→')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="reação">→</button>
                                <button onclick="inserirSimbolo('⇌')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="equilíbrio">⇌</button>
                                <button onclick="inserirSimbolo('⇄')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="reversível">⇄</button>
                                <button onclick="inserirSimbolo('↑')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="gás">↑</button>
                                <button onclick="inserirSimbolo('↓')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="precipitado">↓</button>
                                <button onclick="inserirSimbolo('⇒')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="produz">⇒</button>
                                <button onclick="inserirSimbolo('⇔')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="equilíbrio">⇔</button>
                                <button onclick="inserirSimbolo('Δ')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="calor">Δ</button>
                                <button onclick="inserirSimbolo('⊕')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="aquecimento">⊕</button>
                            </div>
                        </div>
                        
                        <!-- Notação química -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Notação</h3>
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                                <button onclick="inserirSimbolo('ΔH')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">ΔH</button>
                                <button onclick="inserirSimbolo('pH')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">pH</button>
                                <button onclick="inserirSimbolo('pOH')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">pOH</button>
                                <button onclick="inserirSimbolo('°C')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">°C</button>
                                <button onclick="inserirSimbolo('mol')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">mol</button>
                                <button onclick="inserirSimbolo('M')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm" title="mol/L">M</button>
                                <button onclick="inserirSimbolo('+')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">+</button>
                                <button onclick="inserirSimbolo('−')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">−</button>
                                <button onclick="inserirSimbolo('⁺')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">⁺</button>
                                <button onclick="inserirSimbolo('⁻')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">⁻</button>
                                <button onclick="inserirSimbolo('²⁺')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">²⁺</button>
                                <button onclick="inserirSimbolo('²⁻')" class="btn-simbolo px-2 py-2 bg-gray-100 hover:bg-green-100 rounded text-sm">²⁻</button>
                            </div>
                        </div>
                        
                        <!-- Tabela periódica -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">Tabela periódica (clique para inserir)</h3>
                            <div class="overflow-x-auto">
                                <div id="tabela-periodica-grid" class="inline-grid gap-0.5 text-center" style="grid-template-columns: repeat(18, minmax(0, 2rem)); font-size: 0.7rem;">
                                    <!-- Gerada por JS para manter o arquivo menor -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="border-t border-gray-200 p-4 flex justify-end">
                <button onclick="fecharModalSimbolos()" 
                        class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let campoAtivoSimbolos = null;

function abrirModalSimbolos(campoId) {
    campoAtivoSimbolos = document.getElementById(campoId) || document.querySelector(campoId);
    if (!campoAtivoSimbolos) {
        console.error('Campo não encontrado:', campoId);
        return;
    }
    document.getElementById('modalSimbolos').classList.remove('hidden');
    mostrarTabSimbolos('equacoes');
}

function fecharModalSimbolos() {
    document.getElementById('modalSimbolos').classList.add('hidden');
    campoAtivoSimbolos = null;
    var inputLatex = document.getElementById('input-latex-custom');
    if (inputLatex) inputLatex.value = '';
}

function mostrarTabSimbolos(tab) {
    // Atualiza tabs
    document.querySelectorAll('.tab-simbolos').forEach(btn => {
        btn.classList.remove('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
        btn.classList.add('text-gray-600');
    });
    
    const tabBtn = document.getElementById('tab-' + tab);
    if (tabBtn) {
        tabBtn.classList.add('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
        tabBtn.classList.remove('text-gray-600');
    }
    
    // Atualiza conteúdo
    document.querySelectorAll('.tab-conteudo').forEach(div => {
        div.classList.add('hidden');
    });
    
    const conteudo = document.getElementById('conteudo-' + tab);
    if (conteudo) {
        conteudo.classList.remove('hidden');
    }
    
    if (tab === 'quimica') {
        construirTabelaPeriodica();
    }
}

// Símbolos dos elementos (1-118) para a tabela periódica
const elementosPeriodicos = ['H','He','Li','Be','B','C','N','O','F','Ne','Na','Mg','Al','Si','P','S','Cl','Ar','K','Ca','Sc','Ti','V','Cr','Mn','Fe','Co','Ni','Cu','Zn','Ga','Ge','As','Se','Br','Kr','Rb','Sr','Y','Zr','Nb','Mo','Tc','Ru','Rh','Pd','Ag','Cd','In','Sn','Sb','Te','I','Xe','Cs','Ba','La','Ce','Pr','Nd','Pm','Sm','Eu','Gd','Tb','Dy','Ho','Er','Tm','Yb','Lu','Hf','Ta','W','Re','Os','Ir','Pt','Au','Hg','Tl','Pb','Bi','Po','At','Rn','Fr','Ra','Ac','Th','Pa','U','Np','Pu','Am','Cm','Bk','Cf','Es','Fm','Md','No','Lr','Rf','Db','Sg','Bh','Hs','Mt','Ds','Rg','Cn','Nh','Fl','Mc','Lv','Ts','Og'];

function construirTabelaPeriodica() {
    const container = document.getElementById('tabela-periodica-grid');
    if (!container || container.children.length > 0) return;
    elementosPeriodicos.forEach((sym, i) => {
        const num = i + 1;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-simbolo min-w-0 w-full py-1 bg-gray-100 hover:bg-green-100 rounded text-xs border border-gray-200';
        btn.textContent = sym;
        btn.title = num + ' - ' + sym;
        btn.onclick = function() { inserirSimbolo(sym); };
        container.appendChild(btn);
    });
}

function inserirLatexCustom() {
    var input = document.getElementById('input-latex-custom');
    if (!input || !campoAtivoSimbolos) return;
    var latex = (input.value || '').trim();
    if (!latex) return;
    if (latex.indexOf('\\(') !== 0) latex = '\\( ' + latex + ' \\)';
    inserirSimbolo(latex, true);
    input.value = '';
}

function inserirSimbolo(simbolo, naoFechar) {
    if (!campoAtivoSimbolos) {
        console.error('Nenhum campo ativo para inserir símbolo');
        return;
    }
    
    const start = campoAtivoSimbolos.selectionStart != null ? campoAtivoSimbolos.selectionStart : campoAtivoSimbolos.value.length;
    const end = campoAtivoSimbolos.selectionEnd != null ? campoAtivoSimbolos.selectionEnd : campoAtivoSimbolos.value.length;
    const texto = campoAtivoSimbolos.value;
    
    const novoTexto = texto.substring(0, start) + simbolo + texto.substring(end);
    campoAtivoSimbolos.value = novoTexto;
    
    // Reposiciona o cursor
    const novaPosicao = start + simbolo.length;
    campoAtivoSimbolos.setSelectionRange(novaPosicao, novaPosicao);
    campoAtivoSimbolos.focus();
    
    if (!naoFechar) {
        fecharModalSimbolos();
    }
}

// Fecha modal ao clicar fora
document.getElementById('modalSimbolos')?.addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalSimbolos();
    }
});

// Fecha modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('modalSimbolos').classList.contains('hidden')) {
        fecharModalSimbolos();
    }
});
</script>

<style>
.btn-simbolo {
    transition: all 0.2s;
    cursor: pointer;
    font-family: 'Times New Roman', serif;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    text-align: center;
    min-height: 2.5rem;
    min-width: 0;
    width: 100%;
}

.btn-simbolo:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

