(function () {
    'use strict';

    const API_BASE = new URL('../api/', window.location.href).href;

    /** @type {string} */
    let currentRouteId = 'home';

    /** Rota vinda do URL (#ordens); permite F5 manter a tela atual. */
    function routeIdFromLocation() {
        let h = (window.location.hash || '').replace(/^#/, '');
        if (h.startsWith('/')) {
            h = h.substring(1);
        }
        const segment = h.split(/[/?]/)[0] || '';
        if (segment && SPA_ROUTES[segment]) {
            return segment;
        }
        return 'home';
    }

    function syncRouteToUrl(routeId) {
        if (!SPA_ROUTES[routeId]) {
            return;
        }
        const wantHash = routeId === 'home' ? '' : '#' + routeId;
        if (window.location.hash !== wantHash) {
            history.replaceState(
                null,
                '',
                window.location.pathname + window.location.search + wantHash
            );
        }
    }

    let modalOrdem = null;
    let modalOrdemFiltros = null;
    let modalClienteVer = null;
    let modalClienteForm = null;
    let lastClienteViewId = null;
    let modalUsuarioVer = null;
    let modalUsuarioForm = null;
    let lastUsuarioViewId = null;
    let modalAlmoxVer = null;
    let modalAlmoxForm = null;
    let lastAlmoxViewId = null;
    let modalEmbarcacaoVer = null;
    let modalEmbarcacaoForm = null;
    let lastEmbarcacaoViewId = null;
    let modalOrdemEdit = null;
    let modalOrdemNova = null;
    let lastOrdemDetail = null;
    /** @type {Array<Record<string, unknown>>|null} */
    let cachedUsuarios = null;
    /** @type {Array<Record<string, unknown>>|null} */
    let cachedClientes = null;

    /** @type {{ is_gestor?: boolean, nivel_acesso?: string, nome?: string, email?: string, id?: number } | null} */
    let currentUser = null;

    /** @type {Record<string, string>} */
    let ordemListFilters = {};

    const el = (id) => document.getElementById(id);

    const BUSCA_EQUIPAMENTOS_URL = new URL(
        '../../ordens_servico/processamento/busca_equipamentos.php',
        window.location.href
    ).href;

    const SPA_ROUTES = {
        home: { type: 'home', title: 'Painel inicial' },
        ordens: {
            type: 'list',
            title: 'Ordens de Serviço',
            endpoint: 'ordens_servico.php',
            wideLayout: true,
            tableHover: true,
            ordensStats: true,
            ordensFiltros: true,
            ordemRowClick: true,
            ordemActions: true,
            columns: [
                {
                    key: 'numero_os',
                    label: 'Número OS',
                    thClass: 'col-numero',
                    tdClass: 'table-cell-text',
                    attrTitleKey: 'numero_os',
                },
                {
                    key: 'tipo_equipamento',
                    label: 'Tipo',
                    thClass: 'col-tipo',
                    tdClass: 'table-cell-text',
                    html: (row) =>
                        '<span title="' +
                        escapeHtml(labelTipoEquipamento(row.tipo_equipamento)) +
                        '">' +
                        escapeHtml(labelTipoEquipamento(row.tipo_equipamento)) +
                        '</span>',
                },
                {
                    key: 'identificacao_equipamento',
                    label: 'Equipamento',
                    thClass: 'col-equipamento',
                    tdClass: 'table-cell-text',
                    attrTitleKey: 'identificacao_equipamento',
                },
                {
                    key: 'data_abertura',
                    label: 'Data Abertura',
                    format: 'os_dt',
                    thClass: 'col-data',
                    tdClass: 'table-cell-text',
                    attrTitleKey: 'data_abertura',
                },
                {
                    key: 'data_conclusao',
                    label: 'Data Conclusão',
                    thClass: 'col-data',
                    tdClass: 'table-cell-text',
                    html: (row) =>
                        row.data_conclusao
                            ? escapeHtml(formatarDataHoraOsLista(row.data_conclusao))
                            : '<span class="text-muted">-</span>',
                },
                {
                    key: 'nome_cliente',
                    label: 'Executor',
                    thClass: 'col-usuario',
                    tdClass: 'table-cell-text',
                    html: (row) => htmlExecutorOs(row),
                },
                {
                    key: 'status',
                    label: 'Status',
                    thClass: 'table-cell-status',
                    tdClass: 'table-cell-status',
                    html: (row) => badgeStatusOsHtml(row.status),
                },
                {
                    key: 'prioridade',
                    label: 'Prioridade',
                    thClass: 'table-cell-status',
                    tdClass: 'table-cell-status',
                    html: (row) => badgePrioridadeOsHtml(row.prioridade),
                },
                {
                    key: 'nome_usuario_abertura',
                    label: 'Aberto por',
                    thClass: 'col-usuario',
                    tdClass: 'table-cell-text',
                    attrTitleKey: 'nome_usuario_abertura',
                },
            ],
        },
        clientes: {
            type: 'list',
            title: 'Executores',
            endpoint: 'clientes.php',
            clienteActions: true,
            clienteNovo: true,
            columns: [
                { key: 'nome', label: 'Nome' },
                { key: 'cnpj', label: 'CNPJ' },
                { key: 'telefone', label: 'Telefone' },
                { key: 'email', label: 'E-mail' },
                { key: 'data_criacao', label: 'Data Criação', format: 'dt' },
            ],
        },
        embarcacoes: {
            type: 'list',
            title: 'Embarcações',
            endpoint: 'embarcacoes.php',
            embarcacaoNovo: true,
            embarcacaoActions: true,
            columns: [
                { key: 'nome', label: 'Nome', thClass: 'col-nome', tdClass: 'table-cell-text' },
                { key: 'inscricao', label: 'Inscrição', thClass: 'col-inscricao', tdClass: 'table-cell-text' },
                {
                    key: 'tipo',
                    label: 'Tipo',
                    thClass: 'col-tipo',
                    tdClass: 'table-cell-text',
                    html: (row) => htmlTipoEmbarcacao(row),
                },
                { key: 'tag', label: 'Tag', thClass: 'col-tag', tdClass: 'table-cell-text' },
                { key: 'ano_fabricacao', label: 'Ano', thClass: 'col-ano', tdClass: 'table-cell-text' },
                {
                    key: 'capacidade_volumetrica',
                    label: 'Capacidade',
                    thClass: 'table-cell-number',
                    tdClass: 'table-cell-number',
                    html: (row) => htmlCapacidadeM3(row.capacidade_volumetrica),
                },
                { key: 'armador', label: 'Armador', thClass: 'col-armador', tdClass: 'table-cell-text' },
                {
                    key: 'status',
                    label: 'Status',
                    thClass: 'table-cell-status',
                    tdClass: 'table-cell-status',
                    html: (row) => badgeStatusAtivoHtml(row.status),
                },
            ],
        },
        implementos: {
            type: 'list',
            title: 'Implementos',
            endpoint: 'implementos.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'placa', label: 'Placa' },
                { key: 'status', label: 'Status' },
            ],
        },
        tanques: {
            type: 'list',
            title: 'Tanques',
            endpoint: 'tanques.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'localizacao', label: 'Localização' },
                { key: 'capacidade_volumetrica', label: 'Capacidade' },
                { key: 'status', label: 'Status' },
            ],
        },
        veiculos: {
            type: 'list',
            title: 'Veículos',
            endpoint: 'veiculos.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'placa', label: 'Placa' },
                { key: 'modelo', label: 'Modelo' },
                { key: 'status', label: 'Status' },
            ],
        },
        patios: {
            type: 'list',
            title: 'Pátios',
            endpoint: 'patios.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'nome', label: 'Nome' },
                { key: 'localizacao', label: 'Localização' },
                { key: 'area_m2', label: 'Área m²' },
                { key: 'status', label: 'Status' },
            ],
        },
        oficinas: {
            type: 'list',
            title: 'Oficinas',
            endpoint: 'oficinas.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'nome', label: 'Nome' },
                { key: 'localizacao', label: 'Localização' },
                { key: 'status', label: 'Status' },
            ],
        },
        escritorios: {
            type: 'list',
            title: 'Escritórios',
            endpoint: 'escritorios.php',
            columns: [
                { key: 'id', label: 'ID' },
                { key: 'tag', label: 'Tag' },
                { key: 'nome', label: 'Nome' },
                { key: 'localizacao', label: 'Localização' },
                { key: 'status', label: 'Status' },
            ],
        },
        almoxarifado: {
            type: 'list',
            title: 'Almoxarifado',
            endpoint: 'almoxarifado_itens.php',
            tableHover: true,
            almoxGestorActions: true,
            almoxNovo: true,
            columns: [
                {
                    key: 'codigo_barras',
                    label: 'Cód. de Barras',
                    thClass: 'col-nome',
                    tdClass: 'table-cell-text',
                    attrTitleKey: 'codigo_barras',
                },
                { key: 'nome', label: 'Nome', thClass: 'col-nome', tdClass: 'table-cell-text', attrTitleKey: 'nome' },
                {
                    key: 'quantidade',
                    label: 'Quantidade',
                    thClass: 'table-cell-number',
                    tdClass: 'table-cell-number',
                    html: (row) => htmlQuantidadeAlmox(row.quantidade),
                },
                {
                    key: 'valor_unitario',
                    label: 'Valor Unitário',
                    thClass: 'table-cell-number',
                    tdClass: 'table-cell-number',
                    html: (row) => htmlValorUnitarioAlmox(row.valor_unitario),
                },
            ],
        },
        usuarios: {
            type: 'list',
            title: 'Usuários',
            endpoint: 'usuarios.php',
            tableHover: true,
            usuarioActions: true,
            usuarioNovo: true,
            columns: [
                { key: 'nome', label: 'Nome', thClass: 'col-nome', tdClass: 'table-cell-text', attrTitleKey: 'nome' },
                { key: 'email', label: 'Email', thClass: 'col-nome', tdClass: 'table-cell-text', attrTitleKey: 'email' },
                {
                    key: 'nivel_acesso',
                    label: 'Nível de Acesso',
                    thClass: 'col-tipo',
                    tdClass: 'table-cell-text',
                    html: (row) => escapeHtml(formatNivelUsuarioDisplay(row.nivel_acesso)),
                },
                {
                    key: 'data_criacao',
                    label: 'Data Criação',
                    format: 'os_dt',
                    thClass: 'col-data',
                    tdClass: 'table-cell-text',
                },
            ],
        },
        dash_embarcacao: {
            type: 'stub',
            title: 'Dashboard — Embarcações',
            stubBody:
                'Os gráficos deste painel serão carregados pela API de indicadores (migração). Por enquanto use os dados nas listagens de ativos.',
        },
        dash_implemento: {
            type: 'stub',
            title: 'Dashboard — Implementos',
            stubBody:
                'Os gráficos deste painel serão carregados pela API de indicadores (migração). Por enquanto use os dados nas listagens de ativos.',
        },
        dash_tanque: {
            type: 'stub',
            title: 'Dashboard — Tanques',
            stubBody:
                'Os gráficos deste painel serão carregados pela API de indicadores (migração). Por enquanto use os dados nas listagens de ativos.',
        },
        dash_veiculo: {
            type: 'stub',
            title: 'Dashboard — Veículos',
            stubBody:
                'Os gráficos deste painel serão carregados pela API de indicadores (migração). Por enquanto use os dados nas listagens de ativos.',
        },
    };

    async function apiFetch(path, options = {}) {
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };
        if (options.body && typeof options.body === 'string') {
            headers['Content-Type'] = 'application/json';
        }
        const res = await fetch(API_BASE + path, {
            credentials: 'include',
            ...options,
            headers,
        });
        const text = await res.text();
        let data = {};
        if (text) {
            try {
                data = JSON.parse(text);
            } catch {
                data = { error: 'invalid_json', raw: text };
            }
        }
        if (!res.ok) {
            const err = new Error(data.message || res.statusText || 'Erro na requisição');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    function showError(id, message, show) {
        const node = el(id);
        if (!node) return;
        if (show) {
            node.textContent = message;
            node.classList.remove('d-none');
        } else {
            node.textContent = '';
            node.classList.add('d-none');
        }
    }

    function showAppOk(message) {
        const node = el('appOk');
        if (!node) return;
        node.textContent = message;
        node.classList.remove('d-none');
        setTimeout(() => {
            node.classList.add('d-none');
            node.textContent = '';
        }, 3500);
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatarData(data) {
        if (!data) return 'N/A';
        const d = new Date(data);
        if (Number.isNaN(d.getTime())) return 'N/A';
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }

    function formatarDataHoraBr(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    /** Mesmo padrão visual do legado (d/m/Y H:i, sem vírgula antes da hora). */
    function formatarDataHoraOsLista(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '-';
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        const hh = String(d.getHours()).padStart(2, '0');
        const mi = String(d.getMinutes()).padStart(2, '0');
        return dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi;
    }

    function formatCellVal(val, format) {
        if (val === null || val === undefined || val === '') {
            return format === 'dt' || format === 'data' || format === 'os_dt' ? '-' : '-';
        }
        if (format === 'data') return escapeHtml(formatarData(val));
        if (format === 'dt') return escapeHtml(formatarDataHoraBr(val));
        if (format === 'os_dt') return escapeHtml(formatarDataHoraOsLista(val));
        return escapeHtml(String(val));
    }

    function formatNivelUsuarioDisplay(nivel) {
        const n = String(nivel || '');
        if (n === 'gestor' || n === 'admin') return 'Gestor';
        return 'Responsável';
    }

    function htmlExecutorOs(row) {
        if (row.nome_cliente) {
            return '<span class="badge bg-info">' + escapeHtml(String(row.nome_cliente)) + '</span>';
        }
        return '<span class="badge bg-success">Próprio</span>';
    }

    function htmlQuantidadeAlmox(val) {
        const n = parseFloat(String(val ?? '').replace(',', '.'));
        const num = Number.isFinite(n) ? Math.round(n) : 0;
        return escapeHtml(num.toLocaleString('pt-BR'));
    }

    function htmlValorUnitarioAlmox(val) {
        const n = parseFloat(String(val ?? '').replace(',', '.'));
        if (!Number.isFinite(n)) return '-';
        return (
            'R$ ' +
            escapeHtml(
                n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            )
        );
    }

    function renderOrdensStatsHtml(stats) {
        const s = stats || {};
        const num = (x) => {
            const v = parseInt(String(x ?? '0'), 10);
            return Number.isFinite(v) ? v : 0;
        };
        const abertas = num(s.abertas);
        const emAndamento = num(s.em_andamento);
        const prioridadeAlta = num(s.prioridade_alta);
        const concluidas = num(s.concluidas);
        const canceladas = num(s.canceladas);
        const total = num(s.total);
        return (
            '<div class="row g-3 mb-4">' +
            '<div class="col">' +
            '<div class="card text-white bg-warning h-100">' +
            '<div class="card-body"><h6 class="card-title">Abertas</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(abertas)) +
            '</p></div></div></div>' +
            '<div class="col">' +
            '<div class="card text-white bg-info h-100">' +
            '<div class="card-body"><h6 class="card-title">Em Andamento</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(emAndamento)) +
            '</p></div></div></div>' +
            '<div class="col">' +
            '<div class="card text-white bg-danger h-100">' +
            '<div class="card-body"><h6 class="card-title">Prioridade Alta</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(prioridadeAlta)) +
            '</p></div></div></div>' +
            '<div class="col">' +
            '<div class="card text-white bg-success h-100">' +
            '<div class="card-body"><h6 class="card-title">Concluídas</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(concluidas)) +
            '</p></div></div></div>' +
            '<div class="col">' +
            '<div class="card text-white bg-secondary h-100">' +
            '<div class="card-body"><h6 class="card-title">Canceladas</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(canceladas)) +
            '</p></div></div></div>' +
            '<div class="col">' +
            '<div class="card text-white bg-primary h-100">' +
            '<div class="card-body"><h6 class="card-title">Total</h6>' +
            '<p class="card-text display-6">' +
            escapeHtml(String(total)) +
            '</p></div></div></div>' +
            '</div>'
        );
    }

    function ucfirst(s) {
        const t = String(s || '');
        if (!t) return '';
        return t.charAt(0).toUpperCase() + t.slice(1);
    }

    function humanizeSnake(s) {
        return String(s || '')
            .split('_')
            .filter(Boolean)
            .map((w) => ucfirst(w.toLowerCase()))
            .join(' ');
    }

    function badgeWrap(bgClass, text) {
        return '<span class="badge bg-' + escapeHtml(bgClass) + '">' + escapeHtml(text) + '</span>';
    }

    function badgeStatusOsHtml(status) {
        let bg = 'secondary';
        switch (status) {
            case 'aberta':
                bg = 'warning';
                break;
            case 'em_andamento':
                bg = 'info';
                break;
            case 'concluida':
                bg = 'success';
                break;
            case 'cancelada':
                bg = 'danger';
                break;
            default:
                bg = 'secondary';
        }
        return badgeWrap(bg, humanizeSnake(status));
    }

    function badgePrioridadeOsHtml(prioridade) {
        let bg = 'secondary';
        switch (prioridade) {
            case 'baixa':
                bg = 'success';
                break;
            case 'media':
                bg = 'info';
                break;
            case 'alta':
                bg = 'warning';
                break;
            case 'urgente':
            case 'critica':
                bg = 'danger';
                break;
            default:
                bg = 'secondary';
        }
        return badgeWrap(bg, ucfirst(String(prioridade || '')));
    }

    function badgeStatusAtivoHtml(status) {
        const st = String(status || '');
        let bg = 'secondary';
        if (st === 'ativo') bg = 'success';
        else if (st === 'inativo') bg = 'danger';
        else if (st === 'manutencao') bg = 'warning';
        const lab = st === 'manutencao' ? 'Manutenção' : ucfirst(st);
        return badgeWrap(bg, lab);
    }

    function htmlTipoEmbarcacao(row) {
        const tipo = row.tipo != null ? String(row.tipo) : '';
        let h = escapeHtml(tipo);
        if (
            (tipo === 'balsa_simples' || tipo === 'balsa_motorizada') &&
            row.subtipo_balsa
        ) {
            h +=
                '<br><small class="text-muted">' +
                escapeHtml(ucfirst(String(row.subtipo_balsa))) +
                '</small>';
        }
        return h;
    }

    function htmlCapacidadeM3(val) {
        const n = parseFloat(String(val ?? '').replace(',', '.'));
        const num = Number.isFinite(n) ? n : 0;
        const txt = num.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        return escapeHtml(txt) + ' m³';
    }

    function parseJsonStringList(raw) {
        if (raw == null || raw === '') return [];
        if (Array.isArray(raw)) return raw.map((x) => String(x));
        try {
            const v = JSON.parse(String(raw));
            return Array.isArray(v) ? v.map((x) => String(x)) : [];
        } catch {
            return [];
        }
    }

    function nl2brEscaped(s) {
        if (s == null || s === '') return '';
        return escapeHtml(String(s)).replace(/\r\n/g, '\n').replace(/\n/g, '<br>');
    }

    function formatarDataCurtaBr(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '-';
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }

    function formatMoedaBr(val) {
        const n = parseFloat(String(val ?? '').replace(',', '.'));
        if (!Number.isFinite(n)) return '-';
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function labelTipoEquipamento(tipo) {
        const map = {
            embarcacao: 'Embarcação',
            veiculo: 'Veículo',
            implemento: 'Implemento',
            tanque: 'Tanque',
            patio: 'Pátio',
            oficina: 'Oficina',
            escritorio: 'Escritório',
        };
        const t = String(tipo || '');
        return map[t] || ucfirst(t);
    }

    function pdfOsHref(id) {
        return new URL(
            '../../download_os_pdf.php?' + new URLSearchParams({ id: String(id) }),
            window.location.href
        ).href;
    }

    function embarcacaoAcoesHtml(row) {
        const id = row.id;
        return (
            '<div class="btn-group">' +
            '<button type="button" class="btn btn-sm btn-info btn-view-embarcacao" data-id="' +
            escapeHtml(String(id)) +
            '" title="Visualizar"><i class="bi bi-eye"></i></button>' +
            '<button type="button" class="btn btn-sm btn-warning btn-edit-embarcacao" data-id="' +
            escapeHtml(String(id)) +
            '" title="Editar"><i class="bi bi-pencil"></i></button>' +
            '<button type="button" class="btn btn-sm btn-success btn-nova-os-embarcacao" data-id="' +
            escapeHtml(String(id)) +
            '" title="Nova OS"><i class="bi bi-clipboard-plus"></i></button>' +
            '</div>'
        );
    }

    function formatarDias(dias) {
        const n = parseInt(String(dias), 10);
        if (Number.isNaN(n)) return '';
        if (n === 1) return '1 dia';
        return n + ' dias';
    }

    function getCorPrioridade(prioridade) {
        switch (prioridade) {
            case 'urgente':
            case 'critica':
                return 'danger';
            case 'alta':
                return 'warning';
            case 'media':
                return 'info';
            case 'baixa':
                return 'success';
            default:
                return 'secondary';
        }
    }

    function getIconeEquipamento(tipo) {
        switch (tipo) {
            case 'embarcacao':
                return 'bi-water';
            case 'veiculo':
                return 'bi-car-front';
            case 'implemento':
                return 'bi-truck';
            case 'tanque':
                return 'bi-droplet';
            case 'patio':
                return 'bi-grid-1x2';
            case 'oficina':
                return 'bi-tools';
            case 'escritorio':
                return 'bi-building';
            default:
                return 'bi-gear';
        }
    }

    function tipoTitulo(tipo) {
        const t = String(tipo || '');
        if (!t) return '';
        return t.charAt(0).toUpperCase() + t.slice(1);
    }

    function ordemCardLinkHtml(ordem, innerClass, innerHtml) {
        const id = ordem.id;
        return (
            '<a href="#" class="text-decoration-none js-open-ordem" data-ordem-id="' +
            escapeHtml(String(id)) +
            '">' +
            '<div class="' +
            innerClass +
            '">' +
            innerHtml +
            '</div></a>'
        );
    }

    function ordemUrgenteHtml(ordem) {
        const cor = getCorPrioridade(ordem.prioridade);
        const icone = getIconeEquipamento(ordem.tipo_equipamento);
        const inner =
            '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' +
            escapeHtml(String(ordem.numero_os ?? '')) +
            '</strong><br><small><i class="bi ' +
            escapeHtml(icone) +
            ' me-1"></i>' +
            escapeHtml(tipoTitulo(ordem.tipo_equipamento)) +
            ' #' +
            escapeHtml(String(ordem.equipamento_id ?? '')) +
            '</small></div>' +
            '<div class="text-end"><span class="badge bg-' +
            escapeHtml(cor) +
            '">' +
            escapeHtml(String(ordem.prioridade ?? '')) +
            '</span><br><small>' +
            escapeHtml(formatarData(ordem.data_abertura)) +
            '</small></div></div>';
        return ordemCardLinkHtml(ordem, 'alert alert-' + cor + ' py-2 mb-2 cursor-pointer', inner);
    }

    function ordemAntigaHtml(ordem) {
        const icone = getIconeEquipamento(ordem.tipo_equipamento);
        const inner =
            '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' +
            escapeHtml(String(ordem.numero_os ?? '')) +
            '</strong><br><small><i class="bi ' +
            escapeHtml(icone) +
            ' me-1"></i>' +
            escapeHtml(tipoTitulo(ordem.tipo_equipamento)) +
            ' #' +
            escapeHtml(String(ordem.equipamento_id ?? '')) +
            '</small></div>' +
            '<div class="text-end"><span class="badge bg-warning text-dark">' +
            escapeHtml(formatarDias(ordem.dias_aberta)) +
            '</span><br><small>' +
            escapeHtml(formatarData(ordem.data_abertura)) +
            '</small></div></div>';
        return ordemCardLinkHtml(ordem, 'alert alert-warning py-2 mb-2 cursor-pointer', inner);
    }

    function ordemTempoHtml(ordem, badgeClass) {
        const icone = getIconeEquipamento(ordem.tipo_equipamento);
        const inner =
            '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' +
            escapeHtml(String(ordem.numero_os ?? '')) +
            '</strong><br><small><i class="bi ' +
            escapeHtml(icone) +
            ' me-1"></i>' +
            escapeHtml(tipoTitulo(ordem.tipo_equipamento)) +
            '</small></div>' +
            '<div class="text-end"><span class="badge ' +
            escapeHtml(badgeClass) +
            '">' +
            escapeHtml(formatarDias(ordem.dias_aberta)) +
            '</span></div></div>';
        return ordemCardLinkHtml(ordem, 'alert alert-info py-2 mb-2 cursor-pointer', inner);
    }

    function ordemAndamentoHtml(ordem) {
        const icone = getIconeEquipamento(ordem.tipo_equipamento);
        const inner =
            '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' +
            escapeHtml(String(ordem.numero_os ?? '')) +
            '</strong><br><small><i class="bi ' +
            escapeHtml(icone) +
            ' me-1"></i>' +
            escapeHtml(tipoTitulo(ordem.tipo_equipamento)) +
            '</small></div>' +
            '<div class="text-end"><span class="badge bg-warning text-dark">' +
            escapeHtml(formatarDias(ordem.dias_aberta)) +
            '</span></div></div>';
        return ordemCardLinkHtml(ordem, 'alert alert-warning py-2 mb-2 cursor-pointer', inner);
    }

    function ordemConcluidaHtml(ordem) {
        const icone = getIconeEquipamento(ordem.tipo_equipamento);
        const inner =
            '<div class="d-flex justify-content-between align-items-center">' +
            '<div><strong>' +
            escapeHtml(String(ordem.numero_os ?? '')) +
            '</strong><br><small><i class="bi ' +
            escapeHtml(icone) +
            ' me-1"></i>' +
            escapeHtml(tipoTitulo(ordem.tipo_equipamento)) +
            '</small></div>' +
            '<div class="text-end"><span class="badge bg-success">' +
            escapeHtml(formatarDias(ordem.dias_duracao)) +
            '</span></div></div>';
        return ordemCardLinkHtml(ordem, 'alert alert-success py-2 mb-2 cursor-pointer', inner);
    }

    function listaOuVazio(items, renderItem, emptyIconHtml, emptyText) {
        const arr = Array.isArray(items) ? items : [];
        if (!arr.length) {
            return (
                '<div class="text-center text-muted py-3">' +
                emptyIconHtml +
                '<p class="mt-2">' +
                escapeHtml(emptyText) +
                '</p></div>'
            );
        }
        return arr.map(renderItem).join('');
    }

    function renderDashboard(data) {
        const ac = data.alertasCriticos || {};
        const at = data.alertasTempo || {};
        const urgentes = ac.ordens_urgentes || [];
        const antigas = ac.ordens_antigas || [];
        const dias7 = at.ordens_7_dias || [];
        const andamento = at.ordens_andamento_longo || [];
        const concluidas = at.ordens_concluidas_hoje || [];

        return (
            '<div class="row mb-4">' +
            '<div class="col-md-6 mb-3">' +
            '<div class="card h-100">' +
            '<div class="card-header bg-danger text-white">' +
            '<h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Ordens Urgentes/Alta Prioridade</h5></div>' +
            '<div class="card-body" style="max-height:300px;overflow-y:auto">' +
            listaOuVazio(
                urgentes,
                ordemUrgenteHtml,
                '<i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>',
                'Nenhuma ordem urgente!'
            ) +
            '</div></div></div>' +
            '<div class="col-md-6 mb-3">' +
            '<div class="card h-100">' +
            '<div class="card-header bg-warning text-dark">' +
            '<h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Ordens Abertas há Mais de 30 Dias</h5></div>' +
            '<div class="card-body" style="max-height:300px;overflow-y:auto">' +
            listaOuVazio(
                antigas,
                ordemAntigaHtml,
                '<i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>',
                'Nenhuma ordem antiga!'
            ) +
            '</div></div></div>' +
            '</div>' +
            '<div class="row mb-4">' +
            '<div class="col-md-4 mb-3">' +
            '<div class="card h-100">' +
            '<div class="card-header" style="background-color:rgb(202,247,255);color:rgb(42,223,255);">' +
            '<h5 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Ordens há 7+ Dias</h5></div>' +
            '<div class="card-body" style="max-height:250px;overflow-y:auto">' +
            listaOuVazio(
                dias7,
                (o) => ordemTempoHtml(o, 'bg-info'),
                '<i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>',
                'Nenhuma ordem!'
            ) +
            '</div></div></div>' +
            '<div class="col-md-4 mb-3">' +
            '<div class="card h-100">' +
            '<div class="card-header" style="background-color:rgb(255,233,162);color:#856404;">' +
            '<h5 class="mb-0"><i class="bi bi-gear-wide-connected me-2"></i>Em Andamento há 15+ Dias</h5></div>' +
            '<div class="card-body" style="max-height:250px;overflow-y:auto">' +
            listaOuVazio(
                andamento,
                ordemAndamentoHtml,
                '<i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>',
                'Nenhuma ordem!'
            ) +
            '</div></div></div>' +
            '<div class="col-md-4 mb-3">' +
            '<div class="card h-100">' +
            '<div class="card-header" style="background-color:#d4edda;color:#155724;">' +
            '<h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Concluídas Hoje</h5></div>' +
            '<div class="card-body" style="max-height:250px;overflow-y:auto">' +
            listaOuVazio(
                concluidas,
                ordemConcluidaHtml,
                '<i class="bi bi-info-circle text-info" style="font-size: 2rem;"></i>',
                'Nenhuma ordem concluída hoje!'
            ) +
            '</div></div></div>' +
            '</div>'
        );
    }

    async function loadDashboard() {
        showError('appError', '', false);
        const mount = el('dashboardMount');
        if (mount) mount.innerHTML = '<p class="text-muted px-2">Carregando painel…</p>';
        try {
            const data = await apiFetch('home_dashboard.php', { method: 'GET' });
            if (mount) mount.innerHTML = renderDashboard(data);
        } catch (e) {
            const msg =
                e.data && e.data.message ? e.data.message : e.message || 'Erro ao carregar o painel.';
            showError('appError', msg, true);
            if (mount) mount.innerHTML = '';
        }
    }

    function updateMainPanels() {
        const r = SPA_ROUTES[currentRouteId];
        const home = el('spaViewHome');
        const crud = el('spaViewCrud');
        const stub = el('spaViewStub');
        if (!r) return;
        if (r.type === 'home') {
            home.classList.remove('d-none');
            crud.classList.add('d-none');
            stub.classList.add('d-none');
        } else if (r.type === 'list') {
            home.classList.add('d-none');
            crud.classList.remove('d-none');
            stub.classList.add('d-none');
        } else if (r.type === 'stub') {
            home.classList.add('d-none');
            crud.classList.add('d-none');
            stub.classList.remove('d-none');
        }
    }

    function updateNavActive() {
        document.querySelectorAll('#navbarMain a.spa-nav').forEach((a) => {
            const v = a.dataset.spaView;
            const on = v === currentRouteId;
            a.classList.toggle('active', on);
        });
    }

    /**
     * @param {string} routeId
     * @param {{ fromHash?: boolean }} [opts] — se true, não altera o hash (evita loop com hashchange)
     */
    function navigate(routeId, opts) {
        const fromHash = opts && opts.fromHash === true;
        if (!SPA_ROUTES[routeId]) {
            routeId = 'home';
        }
        currentRouteId = routeId;
        const r = SPA_ROUTES[currentRouteId];
        document.title = 'Sistema de Manutenção — ' + r.title;
        updateMainPanels();
        updateNavActive();
        if (r.type === 'home') {
            loadDashboard();
        } else if (r.type === 'list') {
            loadList();
        } else if (r.type === 'stub') {
            el('stubTitle').textContent = r.title;
            el('stubBody').textContent = r.stubBody || '';
        }
        if (!fromHash) {
            syncRouteToUrl(currentRouteId);
        }
    }

    function renderCrudActions(route) {
        const wrap = el('crudActions');
        if (!wrap) return;
        wrap.innerHTML = '';
        if (route.ordensFiltros) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-primary';
            b.innerHTML = '<i class="bi bi-funnel"></i> Filtros';
            b.addEventListener('click', () => {
                syncOrdemFiltrosFormFromState();
                refreshOrdemFiltroAtivosOptions().then(() => {
                    if (modalOrdemFiltros) modalOrdemFiltros.show();
                });
            });
            wrap.appendChild(b);
        }
        if (route.clienteNovo) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-primary';
            b.id = 'btnNovoExecutor';
            b.innerHTML = '<i class="bi bi-plus"></i> Novo Executor';
            b.addEventListener('click', () => openClienteFormModal(null));
            wrap.appendChild(b);
        }
        if (route.embarcacaoNovo) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-primary';
            b.innerHTML = '<i class="bi bi-plus"></i> Nova Embarcação';
            b.addEventListener('click', () => openEmbarcacaoFormModal(null));
            wrap.appendChild(b);
        }
        if (route.almoxNovo && currentUser && currentUser.is_gestor) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-primary';
            b.innerHTML = '<i class="bi bi-plus"></i> Novo Item';
            b.addEventListener('click', () => openAlmoxFormModal(null));
            wrap.appendChild(b);
        }
        if (route.usuarioNovo && currentUser && currentUser.is_gestor) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-primary';
            b.innerHTML = '<i class="bi bi-plus"></i> Novo Usuário';
            b.addEventListener('click', () => openUsuarioFormModal(null));
            wrap.appendChild(b);
        }
    }

    function syncOrdemFiltrosFormFromState() {
        const f = ordemListFilters;
        const setv = (id, v) => {
            const n = el(id);
            if (n) n.value = v != null ? String(v) : '';
        };
        setv('filtroOsTipo', f.tipo || '');
        setv('filtroOsAtivo', f.ativo_id || '');
        setv('filtroOsStatus', f.status || '');
        setv('filtroOsPrioridade', f.prioridade || '');
        setv('filtroOsTipoManut', f.tipo_manutencao || '');
        setv('filtroOsDataAbertura', f.data_abertura || '');
    }

    function readOrdemFiltrosFormToState() {
        const gv = (id) => {
            const n = el(id);
            return n && n.value ? String(n.value).trim() : '';
        };
        ordemListFilters = {};
        const tipo = gv('filtroOsTipo');
        const ativo = gv('filtroOsAtivo');
        const st = gv('filtroOsStatus');
        const pr = gv('filtroOsPrioridade');
        const tm = gv('filtroOsTipoManut');
        const da = gv('filtroOsDataAbertura');
        if (tipo) ordemListFilters.tipo = tipo;
        if (ativo) ordemListFilters.ativo_id = ativo;
        if (st) ordemListFilters.status = st;
        if (pr) ordemListFilters.prioridade = pr;
        if (tm) ordemListFilters.tipo_manutencao = tm;
        if (da) ordemListFilters.data_abertura = da;
    }

    async function refreshOrdemFiltroAtivosOptions() {
        const tipoEl = el('filtroOsTipo');
        const ativoEl = el('filtroOsAtivo');
        if (!tipoEl || !ativoEl) return;
        const tipo = tipoEl.value;
        ativoEl.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = tipo ? 'Todos' : 'Selecione um tipo de equipamento primeiro';
        ativoEl.appendChild(opt0);
        if (!tipo) {
            ativoEl.disabled = true;
            return;
        }
        ativoEl.disabled = false;
        try {
            const res = await fetch(
                BUSCA_EQUIPAMENTOS_URL + '?tipo=' + encodeURIComponent(tipo),
                { credentials: 'include' }
            );
            const data = await res.json();
            if (data.success && Array.isArray(data.equipamentos)) {
                const sel = ordemListFilters.ativo_id ? String(ordemListFilters.ativo_id) : '';
                for (const eq of data.equipamentos) {
                    const o = document.createElement('option');
                    o.value = String(eq.id);
                    o.textContent = String(eq.identificacao ?? eq.id);
                    if (String(eq.id) === sel) o.selected = true;
                    ativoEl.appendChild(o);
                }
            }
        } catch {
            /* legado indisponível */
        }
    }

    async function loadList() {
        const route = SPA_ROUTES[currentRouteId];
        if (!route || route.type !== 'list') return;
        showError('appError', '', false);
        el('crudTitle').textContent = route.title;
        renderCrudActions(route);

        const crudC = el('crudContainer');
        const dataTable = el('dataTable');
        const aboveMount = el('crudAboveTable');
        if (crudC) {
            crudC.className = route.wideLayout ? 'container-fluid px-4' : 'container';
        }
        if (dataTable) {
            let cls = 'table table-striped table-ativos';
            if (route.tableHover) cls += ' table-hover';
            dataTable.className = cls;
        }
        if (aboveMount && !route.ordensStats) {
            aboveMount.classList.add('d-none');
            aboveMount.innerHTML = '';
        }

        const thead = el('dataTableHead');
        const tbody = el('dataTableBody');
        thead.innerHTML = '';
        tbody.innerHTML = '';
        const trh = document.createElement('tr');
        for (const col of route.columns) {
            const th = document.createElement('th');
            th.textContent = col.label;
            if (col.thClass) th.className = col.thClass;
            trh.appendChild(th);
        }
        const showAlmoxActions = route.almoxGestorActions && currentUser && currentUser.is_gestor;
        const hasActions =
            route.clienteActions ||
            route.embarcacaoActions ||
            route.ordemActions ||
            route.usuarioActions ||
            showAlmoxActions;
        if (hasActions) {
            const th = document.createElement('th');
            th.className = 'table-cell-actions';
            th.textContent = 'Ações';
            trh.appendChild(th);
        }
        thead.appendChild(trh);

        try {
            let fetchPath = route.endpoint;
            if (currentRouteId === 'ordens') {
                const p = new URLSearchParams();
                Object.entries(ordemListFilters).forEach(([k, v]) => {
                    if (v != null && String(v) !== '') p.set(k, String(v));
                });
                fetchPath = 'ordens_servico.php' + (p.toString() ? '?' + p.toString() : '');
            }
            const data = await apiFetch(fetchPath, { method: 'GET' });
            const items = data.items || [];

            if (route.ordensStats && aboveMount) {
                aboveMount.classList.remove('d-none');
                aboveMount.innerHTML = renderOrdensStatsHtml(data.stats);
            }

            if (!items.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = trh.children.length;
                td.className = 'text-muted text-center py-4';
                td.textContent = route.ordensStats
                    ? 'Nenhuma ordem de serviço encontrada com os filtros selecionados.'
                    : 'Nenhum registro encontrado.';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }
            for (const row of items) {
                const tr = document.createElement('tr');
                if (route.ordemRowClick) {
                    tr.style.cursor = 'pointer';
                    tr.dataset.ordemId = String(row.id ?? '');
                    tr.addEventListener('click', (ev) => {
                        if (ev.target.closest('.table-cell-actions')) return;
                        const id = parseInt(tr.dataset.ordemId, 10);
                        if (id > 0) openOrdemModal(id);
                    });
                }
                for (const col of route.columns) {
                    const td = document.createElement('td');
                    if (col.tdClass) td.className = col.tdClass;
                    const titleKey = col.attrTitleKey || col.key;
                    if (
                        titleKey &&
                        col.tdClass &&
                        col.tdClass.indexOf('table-cell-text') !== -1 &&
                        col.attrTitle !== false
                    ) {
                        td.title = String(row[titleKey] ?? '');
                    }
                    if (typeof col.html === 'function') td.innerHTML = col.html(row);
                    else td.innerHTML = formatCellVal(row[col.key], col.format);
                    tr.appendChild(td);
                }
                if (route.clienteActions) {
                    const td = document.createElement('td');
                    td.className = 'table-cell-actions';
                    const id = row.id;
                    td.innerHTML =
                        '<div class="btn-group">' +
                        '<button type="button" class="btn btn-sm btn-warning btn-edit-cliente" data-id="' +
                        escapeHtml(String(id)) +
                        '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-info btn-view-cliente" data-id="' +
                        escapeHtml(String(id)) +
                        '" title="Visualizar"><i class="bi bi-eye"></i></button>' +
                        '</div>';
                    tr.appendChild(td);
                } else if (route.embarcacaoActions) {
                    const td = document.createElement('td');
                    td.className = 'table-cell-actions';
                    td.innerHTML = embarcacaoAcoesHtml(row);
                    tr.appendChild(td);
                } else if (route.ordemActions) {
                    const td = document.createElement('td');
                    td.className = 'table-cell-actions';
                    const oid = row.id;
                    td.innerHTML =
                        '<div class="btn-group">' +
                        '<button type="button" class="btn btn-sm btn-warning btn-ordem-edit-open" data-ordem-id="' +
                        escapeHtml(String(oid)) +
                        '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-info btn-ordem-view" data-ordem-id="' +
                        escapeHtml(String(oid)) +
                        '" title="Visualizar"><i class="bi bi-eye"></i></button>' +
                        '</div>';
                    tr.appendChild(td);
                } else if (route.usuarioActions) {
                    const td = document.createElement('td');
                    td.className = 'table-cell-actions';
                    const id = row.id;
                    const g = currentUser && currentUser.is_gestor;
                    let h =
                        '<div class="btn-group">' +
                        '<button type="button" class="btn btn-sm btn-info btn-view-usuario" data-id="' +
                        escapeHtml(String(id)) +
                        '" title="Visualizar"><i class="bi bi-eye"></i></button>';
                    if (g) {
                        h +=
                            '<button type="button" class="btn btn-sm btn-warning btn-edit-usuario" data-id="' +
                            escapeHtml(String(id)) +
                            '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-danger btn-del-usuario" data-id="' +
                            escapeHtml(String(id)) +
                            '" title="Excluir"><i class="bi bi-trash"></i></button>';
                    }
                    h += '</div>';
                    td.innerHTML = h;
                    tr.appendChild(td);
                } else if (showAlmoxActions) {
                    const td = document.createElement('td');
                    td.className = 'table-cell-actions';
                    const aid = row.id;
                    td.innerHTML =
                        '<div class="btn-group">' +
                        '<button type="button" class="btn btn-sm btn-info btn-view-almox" data-id="' +
                        escapeHtml(String(aid)) +
                        '" title="Visualizar"><i class="bi bi-eye"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-warning btn-edit-almox" data-id="' +
                        escapeHtml(String(aid)) +
                        '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-danger btn-del-almox" data-id="' +
                        escapeHtml(String(aid)) +
                        '" title="Excluir"><i class="bi bi-trash"></i></button>' +
                        '</div>';
                    tr.appendChild(td);
                }
                tbody.appendChild(tr);
            }
        } catch (e) {
            const msg =
                e.data && e.data.message ? e.data.message : e.message || 'Erro ao carregar lista.';
            showError('appError', msg, true);
        }
    }

    function renderBlocoJsonLista(titulo, valores) {
        if (!valores.length) return '';
        const li = valores
            .map((v) => '<li><i class="bi bi-check2"></i> ' + escapeHtml(v) + '</li>')
            .join('');
        return (
            '<div class="mb-3">' +
            '<h6>' +
            escapeHtml(titulo) +
            '</h6><ul class="list-unstyled mb-0">' +
            li +
            '</ul></div>'
        );
    }

    function renderOrdemVisualLegacy(item, itens) {
        const tp = labelTipoEquipamento(item.tipo_equipamento);
        const equip =
            item.identificacao_equipamento != null && item.identificacao_equipamento !== ''
                ? String(item.identificacao_equipamento)
                : '-';
        const abertoPor = item.nome_usuario_abertura || item.usuario_abertura_nome || '';
        const gestor = item.nome_gestor || '';
        const responsavel = item.nome_responsavel || item.usuario_responsavel_nome || '';
        const tipoMan = item.tipo_manutencao ? ucfirst(String(item.tipo_manutencao)) : '-';

        let toolbar = '<div class="d-flex flex-wrap gap-2 mb-3">';
        if (item.has_pdf) {
            toolbar +=
                '<a class="btn btn-success btn-sm" href="' +
                escapeHtml(pdfOsHref(item.id)) +
                '" target="_blank" rel="noopener"><i class="bi bi-download"></i> Download PDF</a>';
        }
        if (item.status === 'aberta') {
            toolbar +=
                '<button type="button" class="btn btn-warning btn-sm js-editar-os-aperta" data-ordem-id="' +
                escapeHtml(String(item.id ?? '')) +
                '"><i class="bi bi-pencil"></i> Editar OS</button>';
        }
        toolbar += '</div>';

        const col1 =
            '<p class="mb-2"><strong>Tipo de Equipamento:</strong> ' +
            escapeHtml(tp) +
            '</p>' +
            '<p class="mb-2"><strong>Equipamento:</strong> ' +
            escapeHtml(equip) +
            '</p>' +
            '<p class="mb-2"><strong>Data de Abertura:</strong> ' +
            escapeHtml(formatarDataHoraBr(item.data_abertura)) +
            '</p>' +
            '<p class="mb-2"><strong>Aberto por:</strong> ' +
            escapeHtml(abertoPor || '-') +
            '</p>' +
            '<p class="mb-2"><strong>Gestor:</strong> ' +
            escapeHtml(gestor || '-') +
            '</p>' +
            '<p class="mb-2"><strong>Responsável:</strong> ' +
            escapeHtml(responsavel || '-') +
            '</p>' +
            '<p class="mb-2"><strong>Tipo de Manutenção:</strong> ' +
            escapeHtml(tipoMan) +
            '</p>' +
            '<p class="mb-2"><strong>Prioridade:</strong> ' +
            badgePrioridadeOsHtml(item.prioridade) +
            '</p>';
        let prev = '';
        if (item.data_prevista) {
            prev =
                '<p class="mb-2"><strong>Estimativa de conclusão:</strong> ' +
                escapeHtml(formatarDataCurtaBr(item.data_prevista)) +
                '</p>';
        }
        let odo = '';
        if (
            (item.tipo_equipamento === 'veiculo' || item.tipo_equipamento === 'implemento') &&
            item.odometro != null &&
            item.odometro !== ''
        ) {
            const om = parseFloat(String(item.odometro).replace(',', '.'));
            const txt = Number.isFinite(om)
                ? om.toLocaleString('pt-BR', { maximumFractionDigits: 0 })
                : escapeHtml(String(item.odometro));
            odo = '<p class="mb-0"><strong>Odômetro:</strong> ' + txt + ' km</p>';
        }

        let col2 =
            '<p class="mb-2"><strong>Status:</strong> ' + badgeStatusOsHtml(item.status) + '</p>';
        if (item.data_conclusao) {
            col2 +=
                '<p class="mb-2"><strong>Data de Conclusão:</strong> ' +
                escapeHtml(formatarDataHoraBr(item.data_conclusao)) +
                '</p>';
        }
        if (item.usuario_conclusao) {
            col2 +=
                '<p class="mb-0"><strong>Concluído por:</strong> ' +
                escapeHtml(String(item.usuario_conclusao)) +
                '</p>';
        }

        let prob = '';
        if (item.descricao_problema) {
            prob =
                '<div class="mt-4"><h5 class="h6">Descrição do Problema</h5><p class="mb-0">' +
                nl2brEscaped(item.descricao_problema) +
                '</p></div>';
        }
        let obs = '';
        if (item.observacoes) {
            obs =
                '<div class="mt-4"><h5 class="h6">Observações</h5><p class="mb-0">' +
                nl2brEscaped(item.observacoes) +
                '</p></div>';
        }

        const cardInfo =
            '<div class="card mb-3">' +
            '<div class="card-header"><h5 class="mb-0">Informações da OS</h5></div>' +
            '<div class="card-body">' +
            '<div class="row">' +
            '<div class="col-md-6">' +
            col1 +
            prev +
            odo +
            '</div>' +
            '<div class="col-md-6">' +
            col2 +
            '</div></div>' +
            prob +
            obs +
            '</div></div>';

        const camposJson = [
            ['sistemas_afetados', 'Sistemas Afetados'],
            ['sintomas_detectados', 'Sintomas Detectados'],
            ['causas_defeitos', 'Causas dos Defeitos'],
            ['tipo_intervencao', 'Intervenções Realizadas'],
            ['acoes_realizadas', 'Ações Realizadas'],
        ];
        let detTec = '';
        for (const [field, titulo] of camposJson) {
            detTec += renderBlocoJsonLista(titulo, parseJsonStringList(item[field]));
        }
        if (item.custo_total != null && String(item.custo_total).trim() !== '') {
            const ct = parseFloat(String(item.custo_total).replace(',', '.'));
            const display = Number.isFinite(ct) ? formatMoedaBr(ct) : escapeHtml(String(item.custo_total));
            detTec += '<div class="mt-3"><h6>Custo Total</h6><p class="h5 mb-0">' + display + '</p></div>';
        }
        const cardDet =
            '<div class="card mb-3">' +
            '<div class="card-header"><h5 class="mb-0">Detalhes Técnicos</h5></div>' +
            '<div class="card-body">' +
            (detTec || '<p class="text-muted small mb-0">Nenhum detalhe técnico registrado.</p>') +
            '</div></div>';

        const arr = Array.isArray(itens) ? itens : [];
        let itensCard;
        if (!arr.length) {
            itensCard =
                '<div class="card">' +
                '<div class="card-header"><h5 class="mb-0">Itens da Ordem de Serviço</h5></div>' +
                '<div class="card-body"><p class="text-muted small mb-0">Nenhum item registrado para esta ordem de serviço.</p></div></div>';
        } else {
            let totalGeral = 0;
            let rows = '';
            for (const it of arr) {
                const q = parseFloat(String(it.quantidade ?? '').replace(',', '.'));
                const vu = parseFloat(String(it.valor_unitario ?? '').replace(',', '.'));
                const tq = Number.isFinite(q) ? q : 0;
                const tvu = Number.isFinite(vu) ? vu : 0;
                const totalItem = tq * tvu;
                totalGeral += totalItem;
                const qFmt = tq.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                rows +=
                    '<tr><td>' +
                    formatCellVal(it.descricao, null) +
                    '</td><td>' +
                    escapeHtml(qFmt) +
                    '</td><td>' +
                    formatMoedaBr(tvu) +
                    '</td><td>' +
                    formatMoedaBr(totalItem) +
                    '</td></tr>';
            }
            itensCard =
                '<div class="card">' +
                '<div class="card-header"><h5 class="mb-0">Itens da Ordem de Serviço</h5></div>' +
                '<div class="card-body">' +
                '<div class="table-responsive">' +
                '<table class="table table-bordered table-sm mb-0">' +
                '<thead><tr><th>Item</th><th>Quantidade</th><th>Valor Unitário</th><th>Total</th></tr></thead>' +
                '<tbody>' +
                rows +
                '</tbody>' +
                '<tfoot><tr><td colspan="3" class="text-end"><strong>Total:</strong></td><td><strong>' +
                formatMoedaBr(totalGeral) +
                '</strong></td></tr></tfoot>' +
                '</table></div></div></div>';
        }

        return (
            toolbar +
            '<div class="row g-3">' +
            '<div class="col-lg-8">' +
            cardInfo +
            '</div>' +
            '<div class="col-lg-4">' +
            cardDet +
            '</div></div>' +
            itensCard
        );
    }

    function renderDl(item, labelMap) {
        const keys = Object.keys(item)
            .filter((k) => k !== 'pdf')
            .sort((a, b) => a.localeCompare(b));
        let html = '<dl class="row mb-0">';
        for (const k of keys) {
            const lab = labelMap[k] || k;
            html +=
                '<dt class="col-sm-4 text-muted small">' +
                escapeHtml(lab) +
                '</dt><dd class="col-sm-8">' +
                formatCellVal(item[k], null) +
                '</dd>';
        }
        html += '</dl>';
        return html;
    }

    async function openOrdemModal(id) {
        const body = el('modalOrdemBody');
        const title = el('modalOrdemTitle');
        title.textContent = 'Ordem de serviço #' + id;
        body.innerHTML = '<p class="text-muted">Carregando…</p>';
        lastOrdemDetail = null;
        modalOrdem.show();
        try {
            const data = await apiFetch('ordem_servico.php?id=' + encodeURIComponent(String(id)), { method: 'GET' });
            const item = data.item || {};
            const itens = data.itens || [];
            lastOrdemDetail = item;
            const ref = item.numero_os != null && String(item.numero_os).trim() !== ''
                ? String(item.numero_os)
                : String(item.id ?? id);
            title.textContent = 'Ordem de Serviço #' + ref;
            body.innerHTML = renderOrdemVisualLegacy(item, itens);
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            body.innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p>';
        }
    }

    async function ensureUsuariosCache() {
        if (cachedUsuarios) return cachedUsuarios;
        const d = await apiFetch('usuarios.php', { method: 'GET' });
        cachedUsuarios = d.items || [];
        return cachedUsuarios;
    }

    async function ensureClientesCache() {
        if (cachedClientes) return cachedClientes;
        const d = await apiFetch('clientes.php', { method: 'GET' });
        cachedClientes = d.items || [];
        return cachedClientes;
    }

    function isUsuarioGestorDb(nivel) {
        const n = String(nivel || '');
        return n === 'gestor' || n === 'admin';
    }

    async function fillOsUserSelects(gestorSel, respSel) {
        const items = await ensureUsuariosCache();
        gestorSel.innerHTML = '';
        respSel.innerHTML = '';
        for (const u of items) {
            if (isUsuarioGestorDb(u.nivel_acesso)) {
                const o = document.createElement('option');
                o.value = String(u.id);
                o.textContent = String(u.nome || u.email || u.id);
                gestorSel.appendChild(o);
            }
            const o2 = document.createElement('option');
            o2.value = String(u.id);
            o2.textContent = String(u.nome || u.email || u.id);
            respSel.appendChild(o2);
        }
    }

    async function fillClienteSelect(sel) {
        const items = await ensureClientesCache();
        sel.innerHTML = '<option value="">Selecione…</option>';
        for (const c of items) {
            const o = document.createElement('option');
            o.value = String(c.id);
            o.textContent = String(c.nome || c.id);
            sel.appendChild(o);
        }
    }

    function osDateInputVal(iso) {
        if (!iso) return '';
        const s = String(iso);
        const m = s.match(/^(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : '';
    }

    async function openOrdemEditModal(ordemId) {
        if (!modalOrdemEdit) return;
        showError('formOsError', '', false);
        el('modalOrdemEditTitle').textContent = 'Editar ordem #' + ordemId;
        el('formOsEditId').value = String(ordemId);
        el('modalOrdemEdit').querySelector('.modal-body').scrollTop = 0;
        modalOrdemEdit.show();
        try {
            const data = await apiFetch('ordem_servico.php?id=' + encodeURIComponent(String(ordemId)), { method: 'GET' });
            const item = data.item || {};
            if ((item.status || '') !== 'aberta') {
                modalOrdemEdit.hide();
                showAppOk('Só é possível editar OS abertas.');
                return;
            }
            lastOrdemDetail = item;
            el('formOsTipoEquip').value = String(item.tipo_equipamento || '');
            el('formOsEquipamentoId').value = String(item.equipamento_id ?? '');
            el('formOsTipoEquipLabel').value = labelTipoEquipamento(item.tipo_equipamento);
            el('formOsEquipLabel').value = String(item.identificacao_equipamento || item.equipamento_id || '');
            el('formOsTipoManut').value = String(item.tipo_manutencao || 'preventiva');
            let pr = String(item.prioridade || 'media');
            if (pr === 'critica') pr = 'urgente';
            el('formOsPrioridade').value = pr;
            el('formOsStatus').value = String(item.status || 'aberta');
            el('formOsDataPrev').value = osDateInputVal(item.data_prevista);
            const tipoEq = String(item.tipo_equipamento || '');
            const odoW = el('formOsOdometroWrap');
            const odo = el('formOsOdometro');
            if (tipoEq === 'veiculo' || tipoEq === 'implemento') {
                odoW.classList.remove('d-none');
                odo.value = item.odometro != null && item.odometro !== '' ? String(item.odometro) : '';
            } else {
                odoW.classList.add('d-none');
                odo.value = '';
            }
            el('formOsObs').value = item.observacoes != null ? String(item.observacoes) : '';
            el('formOsDescProb').value = item.descricao_problema != null ? String(item.descricao_problema) : '';
            await fillOsUserSelects(el('formOsGestor'), el('formOsResponsavel'));
            if (item.gestor_id) el('formOsGestor').value = String(item.gestor_id);
            if (item.usuario_responsavel_id) el('formOsResponsavel').value = String(item.usuario_responsavel_id);
            const temCliente = item.cliente_id != null && String(item.cliente_id) !== '' && Number(item.cliente_id) > 0;
            el('formOsTipoProp').value = temCliente ? 'terceiro' : 'proprio';
            await fillClienteSelect(el('formOsCliente'));
            if (temCliente) el('formOsCliente').value = String(item.cliente_id);
            syncOsClienteWrapVisibility();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            showError('formOsError', msg, true);
        }
    }

    function syncOsClienteWrapVisibility() {
        const tp = el('formOsTipoProp').value;
        const w = el('formOsClienteWrap');
        if (tp === 'terceiro') w.classList.remove('d-none');
        else w.classList.add('d-none');
    }

    function syncOsNovaClienteWrapVisibility() {
        const tp = el('formOsNovaTipoProp').value;
        const w = el('formOsNovaClienteWrap');
        if (tp === 'terceiro') w.classList.remove('d-none');
        else w.classList.add('d-none');
    }

    async function saveOrdemEdit() {
        showError('formOsError', '', false);
        const id = parseInt(el('formOsEditId').value, 10);
        if (id < 1) return;
        const tipoProp = el('formOsTipoProp').value;
        const payload = {
            tipo_equipamento: el('formOsTipoEquip').value,
            equipamento_id: parseInt(el('formOsEquipamentoId').value, 10),
            tipo_manutencao: el('formOsTipoManut').value,
            prioridade: el('formOsPrioridade').value,
            status: el('formOsStatus').value,
            gestor_id: parseInt(el('formOsGestor').value, 10),
            usuario_responsavel_id: parseInt(el('formOsResponsavel').value, 10),
            tipo_proprietario: tipoProp,
            cliente_id: tipoProp === 'terceiro' ? parseInt(el('formOsCliente').value, 10) : null,
            data_prevista: el('formOsDataPrev').value || null,
            observacoes: el('formOsObs').value.trim(),
            descricao_problema: el('formOsDescProb').value.trim(),
            sistemas_afetados: [],
            sintomas_detectados: [],
            causas_defeitos: [],
            tipo_intervencao: [],
            acoes_realizadas: [],
        };
        const tipoEq = el('formOsTipoEquip').value;
        if (tipoEq === 'veiculo' || tipoEq === 'implemento') {
            const ov = el('formOsOdometro').value.trim();
            payload.odometro = ov === '' ? null : parseFloat(ov.replace(',', '.'));
        }
        if (tipoProp === 'terceiro' && (!payload.cliente_id || payload.cliente_id < 1)) {
            showError('formOsError', 'Selecione o executor (terceiro).', true);
            return;
        }
        try {
            await apiFetch('ordem_servico.php?id=' + encodeURIComponent(String(id)), {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
            modalOrdemEdit.hide();
            showAppOk('Ordem de serviço atualizada.');
            if (currentRouteId === 'ordens') await loadList();
            const mo = el('modalOrdem');
            if (mo && mo.classList.contains('show')) {
                await openOrdemModal(id);
            }
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao salvar.';
            showError('formOsError', msg, true);
        }
    }

    async function openOrdemNovaModal(embarcacaoId) {
        if (!modalOrdemNova) return;
        showError('formOsNovaError', '', false);
        el('formOsNovaTipoEquip').value = 'embarcacao';
        el('formOsNovaEquipamentoId').value = String(embarcacaoId);
        el('modalOrdemNovaTitle').textContent = 'Nova OS — embarcação #' + embarcacaoId;
        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        el('formOsNovaDataAbertura').value = y + '-' + m + '-' + d;
        el('formOsNovaDataPrev').value = '';
        el('formOsNovaTipoManut').value = 'preventiva';
        el('formOsNovaPrioridade').value = 'media';
        el('formOsNovaTipoProp').value = 'proprio';
        el('formOsNovaDescProb').value = '';
        el('formOsNovaObs').value = '';
        await fillOsUserSelects(el('formOsNovaGestor'), el('formOsNovaResponsavel'));
        if (currentUser && currentUser.id) {
            try {
                el('formOsNovaResponsavel').value = String(currentUser.id);
            } catch {
                /* ok */
            }
        }
        await fillClienteSelect(el('formOsNovaCliente'));
        syncOsNovaClienteWrapVisibility();
        modalOrdemNova.show();
    }

    async function saveOrdemNova() {
        showError('formOsNovaError', '', false);
        const tipoProp = el('formOsNovaTipoProp').value;
        const equipId = parseInt(el('formOsNovaEquipamentoId').value, 10);
        if (equipId < 1) {
            showError('formOsNovaError', 'Equipamento inválido.', true);
            return;
        }
        const payload = {
            tipo_equipamento: el('formOsNovaTipoEquip').value,
            equipamento_id: equipId,
            tipo_manutencao: el('formOsNovaTipoManut').value,
            prioridade: el('formOsNovaPrioridade').value,
            gestor_id: parseInt(el('formOsNovaGestor').value, 10),
            usuario_responsavel_id: parseInt(el('formOsNovaResponsavel').value, 10),
            tipo_proprietario: tipoProp,
            cliente_id: tipoProp === 'terceiro' ? parseInt(el('formOsNovaCliente').value, 10) : null,
            data_abertura: el('formOsNovaDataAbertura').value,
            data_prevista: el('formOsNovaDataPrev').value || null,
            observacoes: el('formOsNovaObs').value.trim(),
            descricao_problema: el('formOsNovaDescProb').value.trim(),
            sistemas_afetados: [],
            sintomas_detectados: [],
            causas_defeitos: [],
            tipo_intervencao: [],
            acoes_realizadas: [],
        };
        if (tipoProp === 'terceiro' && (!payload.cliente_id || payload.cliente_id < 1)) {
            showError('formOsNovaError', 'Selecione o executor (terceiro).', true);
            return;
        }
        try {
            const data = await apiFetch('ordem_servico.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalOrdemNova.hide();
            showAppOk('Ordem de serviço criada.');
            if (currentRouteId === 'ordens') await loadList();
            if (data.id) await openOrdemModal(data.id);
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao criar OS.';
            showError('formOsNovaError', msg, true);
        }
    }

    const labelsUsuario = {
        id: 'ID',
        nome: 'Nome',
        email: 'E-mail',
        nivel_acesso: 'Nível de acesso',
        data_criacao: 'Data criação',
    };

    async function openUsuarioVerModal(id) {
        lastUsuarioViewId = id;
        el('modalUsuarioVerTitle').textContent = 'Usuário #' + id;
        el('modalUsuarioVerBody').innerHTML = '<p class="text-muted">Carregando…</p>';
        modalUsuarioVer.show();
        try {
            const data = await apiFetch('usuario.php?id=' + encodeURIComponent(String(id)), { method: 'GET' });
            const item = data.item || {};
            el('modalUsuarioVerBody').innerHTML = renderDl(item, labelsUsuario);
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            el('modalUsuarioVerBody').innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p>';
        }
    }

    function openUsuarioFormModal(item) {
        showError('formUsuarioError', '', false);
        const isEdit = item && item.id != null;
        el('modalUsuarioFormTitle').textContent = isEdit ? 'Editar usuário' : 'Novo usuário';
        el('formUsuarioEditId').value = isEdit ? String(item.id) : '';
        el('formUsuarioNome').value = item && item.nome != null ? String(item.nome) : '';
        el('formUsuarioEmail').value = item && item.email != null ? String(item.email) : '';
        el('formUsuarioSenha').value = '';
        let nv = item && item.nivel_acesso != null ? String(item.nivel_acesso) : 'responsavel';
        if (nv === 'admin') nv = 'gestor';
        if (nv === 'usuario') nv = 'responsavel';
        el('formUsuarioNivel').value = nv === 'gestor' ? 'gestor' : 'responsavel';
        el('formUsuarioSenhaHint').textContent = isEdit
            ? 'Deixe em branco para manter a senha atual.'
            : 'Obrigatória no cadastro.';
        modalUsuarioForm.show();
    }

    async function saveUsuarioForm() {
        showError('formUsuarioError', '', false);
        const editId = el('formUsuarioEditId').value.trim();
        const payload = {
            nome: el('formUsuarioNome').value.trim(),
            email: el('formUsuarioEmail').value.trim(),
            nivel_acesso: el('formUsuarioNivel').value,
            senha: el('formUsuarioSenha').value,
        };
        if (!payload.nome) {
            showError('formUsuarioError', 'Nome é obrigatório.', true);
            return;
        }
        if (!payload.email) {
            showError('formUsuarioError', 'E-mail é obrigatório.', true);
            return;
        }
        if (!editId && !payload.senha) {
            showError('formUsuarioError', 'Senha é obrigatória no cadastro.', true);
            return;
        }
        try {
            if (editId) {
                const body = { nome: payload.nome, email: payload.email, nivel_acesso: payload.nivel_acesso };
                if (payload.senha) body.senha = payload.senha;
                await apiFetch('usuario.php?id=' + encodeURIComponent(editId), {
                    method: 'PUT',
                    body: JSON.stringify(body),
                });
            } else {
                await apiFetch('usuario.php', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
            }
            modalUsuarioForm.hide();
            cachedUsuarios = null;
            showAppOk(editId ? 'Usuário atualizado.' : 'Usuário cadastrado.');
            if (currentRouteId === 'usuarios') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao salvar.';
            showError('formUsuarioError', msg, true);
        }
    }

    async function deleteUsuario(id) {
        if (!confirm('Excluir este usuário?')) return;
        try {
            await apiFetch('usuario.php?id=' + encodeURIComponent(String(id)), { method: 'DELETE' });
            cachedUsuarios = null;
            showAppOk('Usuário excluído.');
            if (currentRouteId === 'usuarios') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            showError('appError', msg, true);
        }
    }

    const labelsAlmox = {
        id: 'ID',
        codigo_barras: 'Código de barras',
        nome: 'Nome',
        quantidade: 'Quantidade',
        valor_unitario: 'Valor unitário',
    };

    async function openAlmoxVerModal(id) {
        lastAlmoxViewId = id;
        el('modalAlmoxVerTitle').textContent = 'Item #' + id;
        el('modalAlmoxVerBody').innerHTML = '<p class="text-muted">Carregando…</p>';
        modalAlmoxVer.show();
        try {
            const data = await apiFetch('almoxarifado_item.php?id=' + encodeURIComponent(String(id)), { method: 'GET' });
            const item = data.item || {};
            el('modalAlmoxVerBody').innerHTML = renderDl(item, labelsAlmox);
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            el('modalAlmoxVerBody').innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p>';
        }
    }

    function openAlmoxFormModal(item) {
        showError('formAlmoxError', '', false);
        const isEdit = item && item.id != null;
        el('modalAlmoxFormTitle').textContent = isEdit ? 'Editar item' : 'Novo item';
        el('formAlmoxEditId').value = isEdit ? String(item.id) : '';
        el('formAlmoxCodigo').value = item && item.codigo_barras != null ? String(item.codigo_barras) : '';
        el('formAlmoxNome').value = item && item.nome != null ? String(item.nome) : '';
        el('formAlmoxQtd').value =
            item && item.quantidade != null && item.quantidade !== '' ? String(item.quantidade) : '0';
        el('formAlmoxValor').value =
            item && item.valor_unitario != null && item.valor_unitario !== ''
                ? String(item.valor_unitario).replace(',', '.')
                : '0';
        el('formAlmoxCodigo').readOnly = !!isEdit;
        modalAlmoxForm.show();
    }

    async function saveAlmoxForm() {
        showError('formAlmoxError', '', false);
        const editId = el('formAlmoxEditId').value.trim();
        const payload = {
            codigo_barras: el('formAlmoxCodigo').value.trim(),
            nome: el('formAlmoxNome').value.trim(),
            quantidade: parseInt(el('formAlmoxQtd').value, 10),
            valor_unitario: parseFloat(String(el('formAlmoxValor').value).replace(',', '.')),
        };
        if (!payload.nome) {
            showError('formAlmoxError', 'Nome é obrigatório.', true);
            return;
        }
        if (Number.isNaN(payload.quantidade) || payload.quantidade < 0) {
            showError('formAlmoxError', 'Quantidade inválida.', true);
            return;
        }
        if (Number.isNaN(payload.valor_unitario) || payload.valor_unitario < 0) {
            showError('formAlmoxError', 'Valor unitário inválido.', true);
            return;
        }
        try {
            if (editId) {
                await apiFetch('almoxarifado_item.php?id=' + encodeURIComponent(editId), {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
            } else {
                await apiFetch('almoxarifado_item.php', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
            }
            modalAlmoxForm.hide();
            showAppOk(editId ? 'Item atualizado.' : 'Item cadastrado.');
            if (currentRouteId === 'almoxarifado') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao salvar.';
            showError('formAlmoxError', msg, true);
        }
    }

    async function deleteAlmox(id) {
        if (!confirm('Excluir este item?')) return;
        try {
            await apiFetch('almoxarifado_item.php?id=' + encodeURIComponent(String(id)), { method: 'DELETE' });
            showAppOk('Item excluído.');
            if (currentRouteId === 'almoxarifado') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            showError('appError', msg, true);
        }
    }

    function syncEmbarcacaoSubtipoField() {
        const t = el('formEmbTipo').value;
        const wrap = el('formEmbSubtipoWrap');
        const need = t === 'balsa_simples' || t === 'balsa_motorizada';
        if (need) wrap.classList.remove('d-none');
        else wrap.classList.add('d-none');
    }

    async function openEmbarcacaoVerModal(id) {
        lastEmbarcacaoViewId = id;
        el('modalEmbarcacaoVerTitle').textContent = 'Embarcação #' + id;
        el('modalEmbarcacaoVerBody').innerHTML = '<p class="text-muted">Carregando…</p>';
        modalEmbarcacaoVer.show();
        try {
            const data = await apiFetch('embarcacao.php?id=' + encodeURIComponent(String(id)), { method: 'GET' });
            const item = data.item || {};
            const keys = Object.keys(item).filter((k) => k !== 'foto').sort((a, b) => a.localeCompare(b));
            let html = '<dl class="row mb-0">';
            for (const k of keys) {
                html +=
                    '<dt class="col-sm-4 text-muted small">' +
                    escapeHtml(k) +
                    '</dt><dd class="col-sm-8">' +
                    formatCellVal(item[k], null) +
                    '</dd>';
            }
            html += '</dl>';
            el('modalEmbarcacaoVerBody').innerHTML = html;
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            el('modalEmbarcacaoVerBody').innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p>';
        }
    }

    function openEmbarcacaoFormModal(item) {
        showError('formEmbarcacaoError', '', false);
        const isEdit = item && item.id != null;
        el('modalEmbarcacaoFormTitle').textContent = isEdit ? 'Editar embarcação' : 'Nova embarcação';
        el('formEmbarcacaoEditId').value = isEdit ? String(item.id) : '';
        el('formEmbTipo').value = item && item.tipo ? String(item.tipo) : 'empurrador';
        el('formEmbSubtipo').value = item && item.subtipo_balsa ? String(item.subtipo_balsa) : '';
        el('formEmbTag').value = item && item.tag != null ? String(item.tag) : '';
        el('formEmbInscricao').value = item && item.inscricao != null ? String(item.inscricao) : '';
        el('formEmbNome').value = item && item.nome != null ? String(item.nome) : '';
        el('formEmbArmador').value = item && item.armador != null ? String(item.armador) : '';
        el('formEmbAno').value = item && item.ano_fabricacao != null ? String(item.ano_fabricacao) : '';
        el('formEmbCap').value =
            item && item.capacidade_volumetrica != null && item.capacidade_volumetrica !== ''
                ? String(item.capacidade_volumetrica).replace(',', '.')
                : '';
        el('formEmbStatus').value = item && item.status ? String(item.status) : 'ativo';
        syncEmbarcacaoSubtipoField();
        modalEmbarcacaoForm.show();
    }

    async function saveEmbarcacaoForm() {
        showError('formEmbarcacaoError', '', false);
        const editId = el('formEmbarcacaoEditId').value.trim();
        const payload = {
            tipo: el('formEmbTipo').value,
            subtipo_balsa: el('formEmbSubtipo').value || null,
            tag: el('formEmbTag').value.trim(),
            inscricao: el('formEmbInscricao').value.trim(),
            nome: el('formEmbNome').value.trim(),
            armador: el('formEmbArmador').value.trim(),
            ano_fabricacao: el('formEmbAno').value.trim() === '' ? null : parseInt(el('formEmbAno').value, 10),
            capacidade_volumetrica:
                el('formEmbCap').value.trim() === '' ? null : parseFloat(String(el('formEmbCap').value).replace(',', '.')),
            status: el('formEmbStatus').value,
        };
        if (!payload.nome || !payload.tag || !payload.inscricao) {
            showError('formEmbarcacaoError', 'Tag, inscrição e nome são obrigatórios.', true);
            return;
        }
        try {
            if (editId) {
                await apiFetch('embarcacao.php?id=' + encodeURIComponent(editId), {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
            } else {
                await apiFetch('embarcacao.php', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
            }
            modalEmbarcacaoForm.hide();
            showAppOk(editId ? 'Embarcação atualizada.' : 'Embarcação cadastrada.');
            if (currentRouteId === 'embarcacoes') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao salvar.';
            showError('formEmbarcacaoError', msg, true);
        }
    }

    const labelsCliente = {
        id: 'ID',
        nome: 'Nome',
        cnpj: 'CNPJ',
        telefone: 'Telefone',
        email: 'E-mail',
        endereco: 'Endereço',
        data_criacao: 'Data criação',
    };

    async function openClienteVerModal(id) {
        lastClienteViewId = id;
        el('modalClienteVerTitle').textContent = 'Executor #' + id;
        el('modalClienteVerBody').innerHTML = '<p class="text-muted">Carregando…</p>';
        modalClienteVer.show();
        try {
            const data = await apiFetch('cliente.php?id=' + encodeURIComponent(String(id)), { method: 'GET' });
            const item = data.item || {};
            el('modalClienteVerBody').innerHTML = renderDl(item, labelsCliente);
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message;
            el('modalClienteVerBody').innerHTML = '<p class="text-danger">' + escapeHtml(msg) + '</p>';
        }
    }

    function openClienteFormModal(item) {
        showError('formClienteError', '', false);
        const isEdit = item && item.id != null;
        el('modalClienteFormTitle').textContent = isEdit ? 'Editar Executor' : 'Novo Executor';
        el('formClienteEditId').value = isEdit ? String(item.id) : '';
        el('formClienteNome').value = item && item.nome != null ? String(item.nome) : '';
        el('formClienteCnpj').value = item && item.cnpj != null ? String(item.cnpj) : '';
        el('formClienteTelefone').value = item && item.telefone != null ? String(item.telefone) : '';
        el('formClienteEmail').value = item && item.email != null ? String(item.email) : '';
        el('formClienteEndereco').value = item && item.endereco != null ? String(item.endereco) : '';
        modalClienteForm.show();
    }

    async function saveClienteForm() {
        showError('formClienteError', '', false);
        const editId = el('formClienteEditId').value.trim();
        const payload = {
            nome: el('formClienteNome').value.trim(),
            cnpj: el('formClienteCnpj').value.trim(),
            telefone: el('formClienteTelefone').value.trim(),
            email: el('formClienteEmail').value.trim(),
            endereco: el('formClienteEndereco').value.trim(),
        };
        if (!payload.nome) {
            showError('formClienteError', 'Nome é obrigatório.', true);
            return;
        }
        try {
            if (editId) {
                await apiFetch('cliente.php?id=' + encodeURIComponent(editId), {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
            } else {
                await apiFetch('cliente.php', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
            }
            modalClienteForm.hide();
            showAppOk(editId ? 'Executor atualizado.' : 'Executor cadastrado.');
            if (currentRouteId === 'clientes') await loadList();
        } catch (e) {
            const msg = e.data && e.data.message ? e.data.message : e.message || 'Erro ao salvar.';
            showError('formClienteError', msg, true);
        }
    }

    let ordemFiltrosWired = false;

    function wireOrdemFiltrosOnce() {
        if (ordemFiltrosWired) return;
        ordemFiltrosWired = true;
        const tipoEl = el('filtroOsTipo');
        if (tipoEl) {
            tipoEl.addEventListener('change', () => {
                refreshOrdemFiltroAtivosOptions();
            });
        }
        const btnL = el('btnOrdemFiltrosLimpar');
        if (btnL) {
            btnL.addEventListener('click', () => {
                ordemListFilters = {};
                syncOrdemFiltrosFormFromState();
                refreshOrdemFiltroAtivosOptions().then(() => {
                    if (modalOrdemFiltros) modalOrdemFiltros.hide();
                    if (currentRouteId === 'ordens') loadList();
                });
            });
        }
        const btnA = el('btnOrdemFiltrosAplicar');
        if (btnA) {
            btnA.addEventListener('click', () => {
                readOrdemFiltrosFormToState();
                if (modalOrdemFiltros) modalOrdemFiltros.hide();
                if (currentRouteId === 'ordens') loadList();
            });
        }
    }

    function setLoggedInUI(user) {
        currentUser = user || null;
        el('viewLogin').classList.add('d-none');
        el('viewApp').classList.remove('d-none');
        if (user) {
            el('navUserName').textContent = user.nome || '—';
            el('navUserEmail').textContent = user.email || '—';
        }
        if (typeof bootstrap !== 'undefined') {
            if (!modalOrdem) modalOrdem = new bootstrap.Modal(el('modalOrdem'));
            if (!modalOrdemFiltros) modalOrdemFiltros = new bootstrap.Modal(el('modalOrdemFiltros'));
            if (!modalClienteVer) modalClienteVer = new bootstrap.Modal(el('modalClienteVer'));
            if (!modalClienteForm) modalClienteForm = new bootstrap.Modal(el('modalClienteForm'));
            if (!modalUsuarioVer) modalUsuarioVer = new bootstrap.Modal(el('modalUsuarioVer'));
            if (!modalUsuarioForm) modalUsuarioForm = new bootstrap.Modal(el('modalUsuarioForm'));
            if (!modalAlmoxVer) modalAlmoxVer = new bootstrap.Modal(el('modalAlmoxVer'));
            if (!modalAlmoxForm) modalAlmoxForm = new bootstrap.Modal(el('modalAlmoxForm'));
            if (!modalEmbarcacaoVer) modalEmbarcacaoVer = new bootstrap.Modal(el('modalEmbarcacaoVer'));
            if (!modalEmbarcacaoForm) modalEmbarcacaoForm = new bootstrap.Modal(el('modalEmbarcacaoForm'));
            if (!modalOrdemEdit) modalOrdemEdit = new bootstrap.Modal(el('modalOrdemEdit'));
            if (!modalOrdemNova) modalOrdemNova = new bootstrap.Modal(el('modalOrdemNova'));
        }
        wireOrdemFiltrosOnce();
        navigate(routeIdFromLocation(), { fromHash: true });
    }

    function setLoggedOutUI() {
        currentUser = null;
        el('viewLogin').classList.remove('d-none');
        el('viewApp').classList.add('d-none');
        document.title = 'Sistema de Manutenção — Preditix';
        modalOrdem = null;
        modalOrdemFiltros = null;
        modalClienteVer = null;
        modalClienteForm = null;
        modalUsuarioVer = null;
        modalUsuarioForm = null;
        modalAlmoxVer = null;
        modalAlmoxForm = null;
        modalEmbarcacaoVer = null;
        modalEmbarcacaoForm = null;
        modalOrdemEdit = null;
        modalOrdemNova = null;
        cachedUsuarios = null;
        cachedClientes = null;
    }

    async function trySession() {
        try {
            const data = await apiFetch('me.php', { method: 'GET' });
            if (data.user) {
                setLoggedInUI(data.user);
            }
        } catch (e) {
            if (e.status === 401) {
                setLoggedOutUI();
            }
        }
    }

    el('formLogin').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        showError('loginError', '', false);
        const email = el('email').value.trim();
        const senha = el('senha').value;
        try {
            const data = await apiFetch('login.php', {
                method: 'POST',
                body: JSON.stringify({ email, senha }),
            });
            if (data.user) {
                setLoggedInUI(data.user);
                showAppOk('Login realizado.');
            }
        } catch (e) {
            const msg =
                e.data && e.data.message ? e.data.message : e.message || 'Credenciais inválidas.';
            showError('loginError', msg, true);
        }
    });

    el('navbarMain').addEventListener('click', (ev) => {
        const link = ev.target.closest('a.spa-nav');
        if (!link || !link.dataset.spaView) return;
        ev.preventDefault();
        navigate(link.dataset.spaView, {});
    });

    window.addEventListener('hashchange', () => {
        const app = el('viewApp');
        if (!app || app.classList.contains('d-none')) {
            return;
        }
        const id = routeIdFromLocation();
        navigate(id, { fromHash: true });
    });

    el('spaViewHome').addEventListener('click', (ev) => {
        const a = ev.target.closest('a.js-open-ordem');
        if (!a) return;
        ev.preventDefault();
        const id = parseInt(a.getAttribute('data-ordem-id') || '0', 10);
        if (id > 0) openOrdemModal(id);
    });

    el('dataTableBody').addEventListener('click', (ev) => {
        const oEdit = ev.target.closest('.btn-ordem-edit-open');
        const oView = ev.target.closest('.btn-ordem-view');
        if (oEdit) {
            ev.preventDefault();
            ev.stopPropagation();
            const oid = parseInt(oEdit.getAttribute('data-ordem-id') || '0', 10);
            if (oid > 0) openOrdemEditModal(oid);
            return;
        }
        if (oView) {
            ev.preventDefault();
            ev.stopPropagation();
            const oid = parseInt(oView.getAttribute('data-ordem-id') || '0', 10);
            if (oid > 0) openOrdemModal(oid);
            return;
        }
        const ed = ev.target.closest('.btn-edit-cliente');
        const vw = ev.target.closest('.btn-view-cliente');
        if (ed) {
            ev.preventDefault();
            const id = parseInt(ed.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                apiFetch('cliente.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                    .then((data) => openClienteFormModal(data.item || {}))
                    .catch(() => {});
            }
        } else if (vw) {
            ev.preventDefault();
            const id = parseInt(vw.getAttribute('data-id') || '0', 10);
            if (id > 0) openClienteVerModal(id);
            return;
        }
        const uv = ev.target.closest('.btn-view-usuario');
        const ue = ev.target.closest('.btn-edit-usuario');
        const ud = ev.target.closest('.btn-del-usuario');
        if (uv) {
            ev.preventDefault();
            const id = parseInt(uv.getAttribute('data-id') || '0', 10);
            if (id > 0) openUsuarioVerModal(id);
            return;
        }
        if (ue) {
            ev.preventDefault();
            const id = parseInt(ue.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                apiFetch('usuario.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                    .then((data) => openUsuarioFormModal(data.item || {}))
                    .catch(() => {});
            }
            return;
        }
        if (ud) {
            ev.preventDefault();
            const id = parseInt(ud.getAttribute('data-id') || '0', 10);
            if (id > 0) deleteUsuario(id);
            return;
        }
        const av = ev.target.closest('.btn-view-almox');
        const ae = ev.target.closest('.btn-edit-almox');
        const ad = ev.target.closest('.btn-del-almox');
        if (av) {
            ev.preventDefault();
            const id = parseInt(av.getAttribute('data-id') || '0', 10);
            if (id > 0) openAlmoxVerModal(id);
            return;
        }
        if (ae) {
            ev.preventDefault();
            const id = parseInt(ae.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                apiFetch('almoxarifado_item.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                    .then((data) => openAlmoxFormModal(data.item || {}))
                    .catch(() => {});
            }
            return;
        }
        if (ad) {
            ev.preventDefault();
            const id = parseInt(ad.getAttribute('data-id') || '0', 10);
            if (id > 0) deleteAlmox(id);
            return;
        }
        const evb = ev.target.closest('.btn-view-embarcacao');
        const eeb = ev.target.closest('.btn-edit-embarcacao');
        const enb = ev.target.closest('.btn-nova-os-embarcacao');
        if (evb) {
            ev.preventDefault();
            const id = parseInt(evb.getAttribute('data-id') || '0', 10);
            if (id > 0) openEmbarcacaoVerModal(id);
            return;
        }
        if (eeb) {
            ev.preventDefault();
            const id = parseInt(eeb.getAttribute('data-id') || '0', 10);
            if (id > 0) {
                apiFetch('embarcacao.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                    .then((data) => openEmbarcacaoFormModal(data.item || {}))
                    .catch(() => {});
            }
            return;
        }
        if (enb) {
            ev.preventDefault();
            const id = parseInt(enb.getAttribute('data-id') || '0', 10);
            if (id > 0) openOrdemNovaModal(id);
            return;
        }
    });

    el('modalOrdemBody').addEventListener('click', (ev) => {
        const b = ev.target.closest('.js-editar-os-aperta');
        if (!b) return;
        ev.preventDefault();
        const oid = parseInt(b.getAttribute('data-ordem-id') || '0', 10);
        if (oid > 0) {
            if (modalOrdem) modalOrdem.hide();
            openOrdemEditModal(oid);
        }
    });

    el('modalClienteVerEditar').addEventListener('click', () => {
        const id = lastClienteViewId;
        modalClienteVer.hide();
        if (id) {
            apiFetch('cliente.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                .then((data) => openClienteFormModal(data.item || { id }))
                .catch(() => {});
        }
    });

    el('formClienteSubmit').addEventListener('click', () => saveClienteForm());

    el('modalUsuarioVerEditar').addEventListener('click', () => {
        const id = lastUsuarioViewId;
        modalUsuarioVer.hide();
        if (id) {
            apiFetch('usuario.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                .then((data) => openUsuarioFormModal(data.item || { id }))
                .catch(() => {});
        }
    });
    el('formUsuarioSubmit').addEventListener('click', () => saveUsuarioForm());

    el('modalAlmoxVerEditar').addEventListener('click', () => {
        const id = lastAlmoxViewId;
        modalAlmoxVer.hide();
        if (id) {
            apiFetch('almoxarifado_item.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                .then((data) => openAlmoxFormModal(data.item || {}))
                .catch(() => {});
        }
    });
    el('formAlmoxSubmit').addEventListener('click', () => saveAlmoxForm());

    el('modalEmbarcacaoVerEditar').addEventListener('click', () => {
        const id = lastEmbarcacaoViewId;
        modalEmbarcacaoVer.hide();
        if (id) {
            apiFetch('embarcacao.php?id=' + encodeURIComponent(String(id)), { method: 'GET' })
                .then((data) => openEmbarcacaoFormModal(data.item || {}))
                .catch(() => {});
        }
    });
    el('formEmbarcacaoSubmit').addEventListener('click', () => saveEmbarcacaoForm());
    el('formEmbTipo').addEventListener('change', () => syncEmbarcacaoSubtipoField());

    el('formOsTipoProp').addEventListener('change', () => syncOsClienteWrapVisibility());
    el('formOsNovaTipoProp').addEventListener('change', () => syncOsNovaClienteWrapVisibility());
    el('formOsSubmit').addEventListener('click', () => saveOrdemEdit());
    el('formOsNovaSubmit').addEventListener('click', () => saveOrdemNova());

    el('navLogout').addEventListener('click', async (ev) => {
        ev.preventDefault();
        try {
            await apiFetch('logout.php', { method: 'POST', body: '{}' });
        } catch {
            /* ok */
        }
        setLoggedOutUI();
    });

    trySession();
})();
