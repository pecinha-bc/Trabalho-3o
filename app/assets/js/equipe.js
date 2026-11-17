// carrossel da equipe: a escolha so e salva depois da validacao PHP
(() => {
    'use strict';

    const root = document.getElementById('mb-equipe');
    const scriptData = document.getElementById('mb-pokemon-data');
    if (!root || !scriptData) return;

    let pokemons = [];
    let selecionados = [];
    try {
        pokemons = JSON.parse(scriptData.textContent || '[]');
        selecionados = JSON.parse(document.getElementById('mb-initial-selected')?.textContent || '[]');
    } catch (_) {
        return;
    }

    if (!Array.isArray(pokemons)) return;
    const visualizacao = root.dataset.mode === 'visualizacao';
    const $ = id => document.getElementById(id);
    const palco = $('mb-creature');
    const imagem = $('mb-creature-image');
    const nome = $('mb-creature-name');
    const detalhes = $('mb-details-content');
    const lista = $('mb-team-list');
    const miniaturas = $('mb-thumbs');
    const contador = $('mb-team-count');
    const contadorInferior = $('mb-counter');
    const escolha = $('mb-choose');
    const confirmar = $('mb-confirm');
    const idsOcultos = $('mb-hidden-ids');
    const formulario = $('mb-team-form');

    selecionados = selecionados.map(Number).filter(id => pokemons.some(p => Number(p.id) === id)).slice(0, 3);
    let indice = 0;

    const iconeTipo = { fogo: '🔥', agua: '💧', planta: '🌿', eletrico: '⚡', normal: '◉' };
    const rotuloTipo = { fogo: 'Fogo', agua: 'Água', planta: 'Planta', eletrico: 'Elétrico', normal: 'Normal' };
    const placeholder = '../assets/imagens/pokemon-indisponivel.svg';

    function elemento(tag, classe, texto) {
        const novo = document.createElement(tag);
        if (classe) novo.className = classe;
        if (texto !== undefined) novo.textContent = String(texto);
        return novo;
    }

    function imagemPokemon(pokemon, classe) {
        const img = elemento('img', classe || '');
        img.src = pokemon.imagem || placeholder;
        img.alt = pokemon.nome;
        img.loading = 'lazy';
        img.onerror = () => {
            img.onerror = null;
            img.src = placeholder;
        };
        return img;
    }

    function criarEstatistica(rotulo, valor, classe, teto, proporcao = null) {
        const linha = elemento('div', 'mb-stat');
        linha.appendChild(elemento('span', 'mb-stat-label', rotulo));
        const trilho = elemento('div', 'mb-stat-track');
        const preenchimento = elemento('div', `mb-stat-fill ${classe || ''}`);
        const percentual = proporcao === null ? (Number(valor) / teto) * 100 : proporcao * 100;
        preenchimento.style.width = `${Math.min(100, Math.max(0, percentual))}%`;
        trilho.appendChild(preenchimento);
        linha.appendChild(trilho);
        linha.appendChild(elemento('strong', 'mb-stat-value', valor));
        return linha;
    }

    function renderizarDetalhes(pokemon) {
        detalhes.replaceChildren();
        const titulo = elemento('h2', '', pokemon.nome);
        detalhes.appendChild(titulo);
        const tipo = String(pokemon.tipo || '').toLowerCase();
        detalhes.appendChild(elemento('span', `mb-type-badge mb-type-${tipo}`, `${iconeTipo[tipo] || '◉'} ${rotuloTipo[tipo] || tipo}`));
        detalhes.appendChild(elemento('p', 'mb-detail-meta', `Nível ${pokemon.nivel} · ${visualizacao ? `Status: ${pokemon.status}` : 'Disponível para sua equipe'}`));
        // hp usa a proporcao real; os demais atributos usam a escala ate 150 pontos
        detalhes.appendChild(criarEstatistica('HP', `${pokemon.hpAtual}/${pokemon.hp}`, '', 1, pokemon.hp > 0 ? pokemon.hpAtual / pokemon.hp : 0));
        detalhes.appendChild(criarEstatistica('Ataque', pokemon.ataque, 'mb-stat-attack', 150));
        detalhes.appendChild(criarEstatistica('Defesa', pokemon.defesa, 'mb-stat-defense', 150));
        detalhes.appendChild(criarEstatistica('Veloc.', pokemon.velocidade, 'mb-stat-speed', 150));
        detalhes.appendChild(elemento('div', 'mb-moves-title', '⚔ Ataques'));
        if (!pokemon.ataques || pokemon.ataques.length === 0) {
            detalhes.appendChild(elemento('p', 'mb-detail-meta', 'Ataques indisponíveis neste momento.'));
            return;
        }
        pokemon.ataques.forEach(ataque => {
            const linha = elemento('div', 'mb-move');
            linha.appendChild(elemento('span', '', `${iconeTipo[ataque.tipo] || '◉'} ${ataque.nome}`));
            linha.appendChild(elemento('small', '', `Poder ${ataque.poder}`));
            detalhes.appendChild(linha);
        });
    }

    function renderizarEquipe() {
        lista.replaceChildren();
        const quantidade = visualizacao ? pokemons.length : selecionados.length;
        contador.textContent = `(${quantidade}/3)`;
        if (contadorInferior) contadorInferior.textContent = `${selecionados.length} de 3 escolhidos`;
        const equipeAtual = visualizacao
            ? pokemons
            : selecionados.map(id => pokemons.find(p => Number(p.id) === id)).filter(Boolean);

        for (let i = 0; i < 3; i++) {
            const pokemon = equipeAtual[i];
            if (!pokemon) {
                const vazio = elemento('div', 'mb-team-slot is-empty');
                vazio.appendChild(elemento('div', 'mb-team-slot-index', i + 1));
                vazio.appendChild(elemento('span', 'mb-team-slot-info', 'Espaço disponível'));
                lista.appendChild(vazio);
                continue;
            }
            const botao = elemento('button', 'mb-team-slot');
            botao.type = 'button';
            botao.setAttribute('aria-label', `Visualizar ${pokemon.nome}`);
            botao.appendChild(imagemPokemon(pokemon));
            const info = elemento('span', 'mb-team-slot-info');
            info.appendChild(elemento('strong', '', pokemon.nome));
            info.appendChild(elemento('small', '', `${rotuloTipo[pokemon.tipo] || pokemon.tipo} · Nível ${pokemon.nivel}`));
            botao.appendChild(info);
            if (pokemons[indice]?.id === pokemon.id) botao.classList.add('is-current');
            botao.addEventListener('click', () => mostrar(pokemons.findIndex(p => Number(p.id) === Number(pokemon.id))));
            lista.appendChild(botao);
        }
        if (confirmar) confirmar.disabled = selecionados.length !== 3;
        if (idsOcultos) {
            idsOcultos.replaceChildren();
            selecionados.forEach(id => {
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = 'pokemons[]';
                campo.value = String(id);
                idsOcultos.appendChild(campo);
            });
        }
    }

    function renderizarMiniaturas() {
        miniaturas.replaceChildren();
        pokemons.forEach((pokemon, i) => {
            const botao = elemento('button', 'mb-thumb');
            botao.type = 'button';
            botao.title = pokemon.nome;
            botao.setAttribute('aria-label', `Mostrar ${pokemon.nome}`);
            botao.setAttribute('aria-pressed', i === indice ? 'true' : 'false');
            if (i === indice) botao.classList.add('is-current');
            if (!visualizacao && selecionados.includes(Number(pokemon.id))) botao.classList.add('is-selected');
            botao.appendChild(imagemPokemon(pokemon));
            botao.addEventListener('click', () => mostrar(i));
            miniaturas.appendChild(botao);
        });
        const atual = miniaturas.children[indice];
        if (atual) atual.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    }

    function mostrar(novoIndice) {
        if (!pokemons.length) return;
        indice = ((novoIndice % pokemons.length) + pokemons.length) % pokemons.length;
        const pokemon = pokemons[indice];
        imagem.src = pokemon.imagem || placeholder;
        imagem.alt = `Ilustração de ${pokemon.nome}`;
        imagem.onerror = () => {
            imagem.onerror = null;
            imagem.src = placeholder;
        };
        nome.textContent = pokemon.nome;
        palco.style.animation = 'none';
        void palco.offsetHeight;
        palco.style.animation = '';
        renderizarDetalhes(pokemon);
        renderizarEquipe();
        renderizarMiniaturas();
        if (escolha) {
            const estaNaEquipe = selecionados.includes(Number(pokemon.id));
            escolha.textContent = estaNaEquipe ? 'Remover da equipe' : 'Selecionar Pokémon';
            escolha.disabled = !estaNaEquipe && selecionados.length >= 3;
        }
    }

    function mudar(delta) { mostrar(indice + delta); }
    $('mb-prev')?.addEventListener('click', () => mudar(-1));
    $('mb-next')?.addEventListener('click', () => mudar(1));
    $('mb-thumbs-prev')?.addEventListener('click', () => miniaturas.scrollBy({ left: -300, behavior: 'smooth' }));
    $('mb-thumbs-next')?.addEventListener('click', () => miniaturas.scrollBy({ left: 300, behavior: 'smooth' }));

    escolha?.addEventListener('click', () => {
        const id = Number(pokemons[indice].id);
        if (selecionados.includes(id)) {
            selecionados = selecionados.filter(item => item !== id);
        } else if (selecionados.length < 3) {
            selecionados.push(id);
        }
        mostrar(indice);
    });

    formulario?.addEventListener('submit', evento => {
        if (selecionados.length !== 3) {
            evento.preventDefault();
            return;
        }
        if (!window.confirm('Confirmar os três Pokémon? Essa escolha inicial será permanente.')) {
            evento.preventDefault();
        }
    });

    document.addEventListener('keydown', evento => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        if (evento.key === 'ArrowLeft') mudar(-1);
        if (evento.key === 'ArrowRight') mudar(1);
    });

    let toqueInicio = null;
    palco?.addEventListener('touchstart', evento => {
        toqueInicio = evento.changedTouches[0]?.screenX ?? null;
    }, { passive: true });
    palco?.addEventListener('touchend', evento => {
        if (toqueInicio === null) return;
        const deslocamento = (evento.changedTouches[0]?.screenX ?? toqueInicio) - toqueInicio;
        if (Math.abs(deslocamento) >= 45) mudar(deslocamento < 0 ? 1 : -1);
        toqueInicio = null;
    }, { passive: true });

    if (!pokemons.length) {
        nome.textContent = 'Nenhum Pokémon disponível';
        if (escolha) escolha.disabled = true;
        if (confirmar) confirmar.disabled = true;
        return;
    }

    mostrar(0);
})();
