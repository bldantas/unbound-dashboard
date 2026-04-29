<?php
/**
 * Wizard de Instalação Multi-Step Premium
 * Substitui o setup.php antigo
 */

// Guard: se já está instalado, redireciona
if (file_exists(__DIR__ . '/data/.installed')) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação — Unbound Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --bg-primary: #0b1120;
            --bg-secondary: #111827;
            --bg-card: rgba(30, 41, 59, 0.5);
            --bg-card-hover: rgba(30, 41, 59, 0.7);
            --border-subtle: rgba(255, 255, 255, 0.06);
            --border-glow: rgba(59, 130, 246, 0.3);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-blue: #3b82f6;
            --accent-blue-glow: rgba(59, 130, 246, 0.15);
            --accent-green: #10b981;
            --accent-green-glow: rgba(16, 185, 129, 0.15);
            --accent-amber: #f59e0b;
            --accent-amber-glow: rgba(245, 158, 11, 0.15);
            --accent-red: #ef4444;
            --accent-red-glow: rgba(239, 68, 68, 0.15);
            --accent-purple: #8b5cf6;
            --accent-cyan: #06b6d4;
        }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
        }

        /* Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* ===== MAIN CONTAINER ===== */
        .wizard-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 680px;
        }

        /* ===== LOGO AREA ===== */
        .wizard-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .wizard-logo .icon-wrap {
            width: 64px; height: 64px;
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            animation: logoFloat 3s ease-in-out infinite;
        }
        .wizard-logo .icon-wrap svg { width: 32px; height: 32px; color: var(--accent-blue); }
        .wizard-logo h1 { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.025em; }
        .wizard-logo p { color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px; }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        /* ===== STEPPER ===== */
        .stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 32px;
            gap: 0;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid var(--border-subtle);
            background: var(--bg-secondary);
            color: var(--text-muted);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .step-circle.active {
            border-color: var(--accent-blue);
            background: var(--accent-blue-glow);
            color: var(--accent-blue);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.2);
        }
        .step-circle.done {
            border-color: var(--accent-green);
            background: var(--accent-green);
            color: #fff;
        }
        .step-circle.done::after {
            content: '✓';
            font-size: 0.9rem;
        }
        .step-circle.done span { display: none; }
        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: none;
        }
        @media (min-width: 640px) {
            .step-label { display: block; }
        }
        .step-line {
            width: 40px; height: 2px;
            background: var(--border-subtle);
            margin: 0 4px;
            transition: background 0.4s ease;
        }
        .step-line.done { background: var(--accent-green); }

        /* ===== CARD ===== */
        .wizard-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .wizard-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.3), transparent);
        }

        /* ===== STEP PANELS ===== */
        .step-panel {
            display: none;
            animation: stepFadeIn 0.4s ease;
        }
        .step-panel.active { display: block; }

        @keyframes stepFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-header {
            margin-bottom: 28px;
        }
        .step-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .step-header p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* ===== CHECK GRID ===== */
        .check-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            gap: 14px;
            transition: all 0.3s ease;
        }
        .check-item.status-ok { border-color: rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.04); }
        .check-item.status-warning { border-color: rgba(245, 158, 11, 0.2); background: rgba(245, 158, 11, 0.04); }
        .check-item.status-error { border-color: rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.04); }
        .check-item.status-pending { opacity: 0.5; }

        .check-icon {
            width: 32px; height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .status-ok .check-icon { background: var(--accent-green-glow); color: var(--accent-green); }
        .status-warning .check-icon { background: var(--accent-amber-glow); color: var(--accent-amber); }
        .status-error .check-icon { background: var(--accent-red-glow); color: var(--accent-red); }
        .status-pending .check-icon { background: rgba(100,116,139,.15); color: var(--text-muted); }

        .check-info { flex: 1; }
        .check-info .label { font-size: 0.85rem; font-weight: 600; }
        .check-info .detail { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.25s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px var(--accent-blue-glow);
        }
        .form-input::placeholder { color: var(--text-muted); }
        .form-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 6px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 500px) {
            .form-row { grid-template-columns: 1fr; }
        }

        /* ===== PASSWORD STRENGTH ===== */
        .password-strength {
            height: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 99px;
            margin-top: 8px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 99px;
            transition: all 0.3s ease;
        }
        .strength-weak { width: 25%; background: var(--accent-red); }
        .strength-fair { width: 50%; background: var(--accent-amber); }
        .strength-good { width: 75%; background: var(--accent-blue); }
        .strength-strong { width: 100%; background: var(--accent-green); }

        /* ===== UNBOUND INFO ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        @media (max-width: 500px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        .info-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 16px;
        }
        .info-card .info-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .info-card .info-value {
            font-size: 0.95rem;
            font-weight: 600;
        }
        .info-card .info-value.online { color: var(--accent-green); }
        .info-card .info-value.offline { color: var(--accent-red); }

        /* ===== BUTTONS ===== */
        .btn-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 28px;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 14px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .btn-primary {
            background: var(--accent-blue);
            color: #fff;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
            box-shadow: 0 6px 24px rgba(59, 130, 246, 0.4);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.06);
            color: var(--text-secondary);
            border: 1px solid var(--border-subtle);
        }
        .btn-secondary:hover:not(:disabled) {
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
        }
        .btn-success {
            background: var(--accent-green);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover:not(:disabled) {
            background: #059669;
            transform: translateY(-1px);
        }
        .btn svg { width: 16px; height: 16px; }

        /* ===== ALERTS ===== */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: none;
        }
        .alert.show { display: block; animation: stepFadeIn 0.3s ease; }
        .alert-error {
            background: var(--accent-red-glow);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
        }
        .alert-success {
            background: var(--accent-green-glow);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #6ee7b7;
        }

        /* ===== SPINNER ===== */
        .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: none;
        }
        .spinner.show { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== PROGRESS STEPS (Step 2 DB) ===== */
        .progress-steps {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
        }
        .progress-step {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .progress-step.done {
            color: var(--accent-green);
            background: rgba(16, 185, 129, 0.05);
        }
        .progress-step.error {
            color: var(--accent-red);
            background: rgba(239, 68, 68, 0.05);
        }
        .progress-step .step-icon { font-size: 1rem; }

        /* ===== FINAL CHECKLIST ===== */
        .final-checklist {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 24px 0;
        }
        .final-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            opacity: 0;
            transform: translateX(-8px);
            transition: all 0.4s ease;
        }
        .final-item.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .final-item .fi-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .final-item.fi-ok .fi-icon { background: var(--accent-green-glow); color: var(--accent-green); }
        .final-item.fi-warning .fi-icon { background: var(--accent-amber-glow); color: var(--accent-amber); }
        .final-item.fi-error .fi-icon { background: var(--accent-red-glow); color: var(--accent-red); }
        .final-item .fi-text { flex: 1; }
        .final-item .fi-label { font-size: 0.85rem; font-weight: 600; }
        .final-item .fi-detail { font-size: 0.72rem; color: var(--text-secondary); margin-top: 2px; }

        /* ===== SUCCESS ANIMATION ===== */
        .success-badge {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: var(--accent-green-glow);
            border: 2px solid var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            animation: successPulse 1.5s ease-in-out infinite;
        }
        @keyframes successPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.3); }
            50% { box-shadow: 0 0 0 16px rgba(16, 185, 129, 0); }
        }

        /* ===== FOOTER ===== */
        .wizard-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.72rem;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="wizard-container">
        <!-- LOGO -->
        <div class="wizard-logo">
            <div class="icon-wrap">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
            </div>
            <h1>Unbound Dashboard</h1>
            <p>Assistente de Instalação</p>
        </div>

        <!-- STEPPER -->
        <div class="stepper" id="stepper">
            <div class="step-item">
                <div class="step-circle active" data-step="1"><span>1</span></div>
                <span class="step-label">Ambiente</span>
            </div>
            <div class="step-line" data-line="1"></div>
            <div class="step-item">
                <div class="step-circle" data-step="2"><span>2</span></div>
                <span class="step-label">Banco</span>
            </div>
            <div class="step-line" data-line="2"></div>
            <div class="step-item">
                <div class="step-circle" data-step="3"><span>3</span></div>
                <span class="step-label">DNS</span>
            </div>
            <div class="step-line" data-line="3"></div>
            <div class="step-item">
                <div class="step-circle" data-step="4"><span>4</span></div>
                <span class="step-label">Admin</span>
            </div>
            <div class="step-line" data-line="4"></div>
            <div class="step-item">
                <div class="step-circle" data-step="5"><span>5</span></div>
                <span class="step-label">Finalizar</span>
            </div>
        </div>

        <!-- CARD -->
        <div class="wizard-card">
            <!-- ======= STEP 1: Ambiente ======= -->
            <div class="step-panel active" data-panel="1">
                <div class="step-header">
                    <h2>🔍 Verificação do Ambiente</h2>
                    <p>Verificaremos se todos os requisitos estão atendidos para a instalação do dashboard.</p>
                </div>
                <div id="alert-step1" class="alert"></div>
                <div class="check-grid" id="env-checks">
                    <!-- Preenchido via JS -->
                </div>
                <div class="btn-row">
                    <div></div>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <button class="btn btn-secondary" onclick="runEnvCheck()" id="btn-env-check">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Verificar
                        </button>
                        <button class="btn btn-primary" onclick="goToStep(2)" id="btn-next-1" disabled>
                            Próximo
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ======= STEP 2: Banco de Dados ======= -->
            <div class="step-panel" data-panel="2">
                <div class="step-header">
                    <h2>🗄️ Configuração do Banco de Dados</h2>
                    <p>Informe as credenciais do MariaDB/MySQL para criar o banco do sistema.</p>
                </div>
                <div id="alert-step2" class="alert"></div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Host</label>
                        <input type="text" class="form-input" id="db_host" value="127.0.0.1" placeholder="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Porta</label>
                        <input type="number" class="form-input" id="db_port" value="3306" placeholder="3306">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Usuário Root MySQL</label>
                        <input type="text" class="form-input" id="db_root_user" value="root" placeholder="root">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Senha Root MySQL</label>
                        <input type="password" class="form-input" id="db_root_pass" placeholder="••••••">
                        <span class="form-hint">Deixe em branco se o root sem senha (local)</span>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border-subtle);margin:20px 0;padding-top:20px;">
                    <p style="font-size:0.78rem;font-weight:600;color:var(--text-secondary);margin-bottom:14px;text-transform:uppercase;letter-spacing:0.05em;">Usuário Dedicado do Aplicativo</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Usuário App</label>
                        <input type="text" class="form-input" id="db_app_user" value="unbounddb" placeholder="unbounddb">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Senha App</label>
                        <input type="text" class="form-input" id="db_app_pass" value="unbounddash" placeholder="unbounddash">
                            <span class="form-hint">Será usada na conexão do Dashboard</span>
                        </div>
                    </div>
                </div>

                <div class="progress-steps" id="db-progress" style="display:none;"></div>

                <div class="btn-row">
                    <button class="btn btn-secondary" onclick="goToStep(1)">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Voltar
                    </button>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <div class="spinner" id="spinner-db"></div>
                        <button class="btn btn-primary" onclick="configureDatabase()" id="btn-db-configure">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                            Criar Banco & Importar
                        </button>
                    </div>
                </div>
            </div>

            <!-- ======= STEP 3: Unbound ======= -->
            <div class="step-panel" data-panel="3">
                <div class="step-header">
                    <h2>🌐 Servidor DNS (Unbound)</h2>
                    <p>Diagnóstico do estado atual do servidor Unbound. Configurações avançadas podem ser feitas após o setup, no menu de Configurações.</p>
                </div>
                <div id="alert-step3" class="alert"></div>

                <div class="info-grid" id="unbound-info">
                    <!-- Preenchido via JS -->
                </div>

                <div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);border-radius:12px;padding:14px 18px;font-size:0.8rem;color:var(--text-secondary);">
                    <strong style="color:var(--accent-blue);">💡 Dica:</strong> Após concluir a instalação, acesse <strong>Configurações → DNS</strong> para personalizar forwarders, rate-limit, DNSSEC e demais opções.
                </div>

                <div class="btn-row">
                    <button class="btn btn-secondary" onclick="goToStep(2)">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Voltar
                    </button>
                    <button class="btn btn-primary" onclick="goToStep(4)" id="btn-next-3">
                        Próximo
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- ======= STEP 4: Admin ======= -->
            <div class="step-panel" data-panel="4">
                <div class="step-header">
                    <h2>👤 Criar Conta de Administrador</h2>
                    <p>Defina as credenciais do primeiro administrador do sistema.</p>
                </div>
                <div id="alert-step4" class="alert"></div>

                <div class="form-group">
                    <label class="form-label">Nome de Usuário</label>
                    <input type="text" class="form-input" id="admin_username" placeholder="admin" minlength="3">
                </div>
                <div class="form-group">
                    <label class="form-label">Email (Opcional)</label>
                    <input type="email" class="form-input" id="admin_email" placeholder="admin@empresa.com">
                    <span class="form-hint">Usado para recuperação de senha</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-input" id="admin_password" placeholder="••••••••" minlength="6" oninput="updatePasswordStrength()">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="password-bar"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Senha</label>
                        <input type="password" class="form-input" id="admin_password_confirm" placeholder="••••••••" minlength="6">
                    </div>
                </div>

                <div class="btn-row">
                    <button class="btn btn-secondary" onclick="goToStep(3)">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Voltar
                    </button>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <div class="spinner" id="spinner-admin"></div>
                        <button class="btn btn-primary" onclick="createAdmin()" id="btn-create-admin">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Criar Administrador
                        </button>
                    </div>
                </div>
            </div>

            <!-- ======= STEP 5: Finalizar ======= -->
            <div class="step-panel" data-panel="5">
                <div class="step-header" style="text-align:center;">
                    <div class="success-badge" id="final-badge">🚀</div>
                    <h2 id="final-title">Finalizando Instalação...</h2>
                    <p id="final-subtitle">Verificando todos os componentes do sistema.</p>
                </div>
                <div id="alert-step5" class="alert"></div>

                <div class="final-checklist" id="final-checklist">
                    <!-- Preenchido via JS -->
                </div>

                <div class="btn-row" id="final-buttons" style="display:none;justify-content:center;">
                    <a href="login.php" class="btn btn-success" id="btn-finish">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        Acessar Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="wizard-footer">
            Unbound Dashboard — Assistente de Instalação v<?= trim(file_get_contents(__DIR__ . '/VERSION') ?: '1.0.0') ?>
        </div>
    </div>

    <script>
    // ===== STATE =====
    let currentStep = 1;
    const stepCompleted = { 1: false, 2: false, 3: false, 4: false, 5: false };
    const API_URL = 'api/setup_wizard.php';

    // ===== NAVIGATION =====
    function goToStep(step) {
        // Não permite pular etapas não completadas (exceto retroceder)
        if (step > currentStep + 1) return;
        if (step > currentStep && !stepCompleted[currentStep] && step !== currentStep + 1) return;

        currentStep = step;

        // Atualizar painéis
        document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
        document.querySelector(`[data-panel="${step}"]`).classList.add('active');

        // Atualizar stepper
        document.querySelectorAll('.step-circle').forEach(c => {
            const s = parseInt(c.dataset.step);
            c.classList.remove('active', 'done');
            if (s === step) c.classList.add('active');
            else if (s < step) c.classList.add('done');
        });
        document.querySelectorAll('.step-line').forEach(l => {
            const s = parseInt(l.dataset.line);
            l.classList.toggle('done', s < step);
        });

        // Ações automáticas por step
        if (step === 1 && !stepCompleted[1]) runEnvCheck();
        if (step === 3) loadUnboundInfo();
        if (step === 5) runFinalize();
    }

    // ===== STEP 1: ENV CHECK =====
    async function runEnvCheck() {
        const grid = document.getElementById('env-checks');
        const btn = document.getElementById('btn-env-check');
        const btnNext = document.getElementById('btn-next-1');
        
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner show"></div> Verificando...';
        grid.innerHTML = '';
        hideAlert('step1');

        try {
            const resp = await fetch(API_URL + '?action=check_environment');
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Erro na verificação');

            data.checks.forEach(check => {
                const statusIcons = { ok: '✓', warning: '⚠', error: '✗', pending: '…' };
                grid.innerHTML += `
                    <div class="check-item status-${check.status}">
                        <div class="check-icon">${statusIcons[check.status]}</div>
                        <div class="check-info">
                            <div class="label">${check.label}</div>
                            <div class="detail">${check.detail}${check.critical ? '' : ' <em>(opcional)</em>'}</div>
                        </div>
                    </div>
                `;
            });

            if (data.can_proceed) {
                btnNext.disabled = false;
                stepCompleted[1] = true;
                showAlert('step1', 'Todos os requisitos críticos foram atendidos!', 'success');
            } else {
                btnNext.disabled = true;
                showAlert('step1', 'Corrija os itens em vermelho antes de prosseguir.', 'error');
            }
        } catch (err) {
            showAlert('step1', 'Erro ao verificar ambiente: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Re-verificar';
        }
    }

    // ===== STEP 2: DATABASE =====
    async function configureDatabase() {
        const btn = document.getElementById('btn-db-configure');
        const spinner = document.getElementById('spinner-db');
        const progress = document.getElementById('db-progress');
        
        const appPass = document.getElementById('db_app_pass').value.trim();
        if (!appPass) {
            showAlert('step2', 'Informe uma senha para o usuário do aplicativo.', 'error');
            return;
        }

        btn.disabled = true;
        spinner.classList.add('show');
        progress.style.display = 'flex';
        progress.innerHTML = '<div class="progress-step"><span class="step-icon">⏳</span> Processando...</div>';
        hideAlert('step2');

        const formData = new FormData();
        formData.append('action', 'configure_database');
        formData.append('db_host', document.getElementById('db_host').value);
        formData.append('db_port', document.getElementById('db_port').value);
        formData.append('db_root_user', document.getElementById('db_root_user').value);
        formData.append('db_root_pass', document.getElementById('db_root_pass').value);
        formData.append('db_app_user', document.getElementById('db_app_user').value);
        formData.append('db_app_pass', appPass);

        try {
            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const data = await resp.json();

            progress.innerHTML = '';
            if (data.steps) {
                data.steps.forEach(s => {
                    const icon = s.status === 'ok' ? '✓' : '✗';
                    progress.innerHTML += `<div class="progress-step ${s.status === 'ok' ? 'done' : 'error'}"><span class="step-icon">${icon}</span> ${s.label}</div>`;
                });
            }

            if (data.success) {
                stepCompleted[2] = true;
                showAlert('step2', data.message, 'success');
                // Auto-avançar após 1.5s
                setTimeout(() => goToStep(3), 1500);
            } else {
                showAlert('step2', data.message, 'error');
            }
        } catch (err) {
            showAlert('step2', 'Erro de comunicação: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            spinner.classList.remove('show');
        }
    }

    // ===== STEP 3: UNBOUND INFO =====
    async function loadUnboundInfo() {
        const grid = document.getElementById('unbound-info');
        
        try {
            const resp = await fetch(API_URL + '?action=check_unbound');
            const data = await resp.json();
            
            if (!data.success) throw new Error(data.message);

            const info = data.info;
            const isOnline = info.daemon_status === 'online';
            
            grid.innerHTML = `
                <div class="info-card">
                    <div class="info-label">Status do Daemon</div>
                    <div class="info-value ${isOnline ? 'online' : 'offline'}">${isOnline ? '● Online' : '● Offline'}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Versão</div>
                    <div class="info-value">${info.version || 'N/A'}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Interfaces</div>
                    <div class="info-value">${info.interfaces.length > 0 ? info.interfaces.join(', ') : 'Padrão'}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Forwarders</div>
                    <div class="info-value">${info.forwarders.length > 0 ? info.forwarders.join(', ') : 'Nenhum (recursivo)'}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Multicore</div>
                    <div class="info-value">${info.multicore_instances > 0 ? info.multicore_instances + ' instância(s)' : 'Single-core'}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Segurança</div>
                    <div class="info-value">${[info.dnssec ? 'DNSSEC' : '', info.tls_certs ? 'TLS' : ''].filter(Boolean).join(' + ') || 'Básica'}</div>
                </div>
            `;

            stepCompleted[3] = true;
        } catch (err) {
            grid.innerHTML = `<div class="info-card" style="grid-column:span 2;text-align:center;">
                <div class="info-label">Diagnóstico</div>
                <div class="info-value offline">Não foi possível verificar o Unbound</div>
            </div>`;
            // Mesmo assim permite prosseguir
            stepCompleted[3] = true;
        }
    }

    // ===== STEP 4: CREATE ADMIN =====
    async function createAdmin() {
        const btn = document.getElementById('btn-create-admin');
        const spinner = document.getElementById('spinner-admin');
        
        const username = document.getElementById('admin_username').value.trim();
        const email = document.getElementById('admin_email').value.trim();
        const password = document.getElementById('admin_password').value;
        const confirm = document.getElementById('admin_password_confirm').value;

        if (!username || username.length < 3) {
            showAlert('step4', 'Nome de usuário deve ter pelo menos 3 caracteres.', 'error');
            return;
        }
        if (!password || password.length < 6) {
            showAlert('step4', 'A senha deve ter pelo menos 6 caracteres.', 'error');
            return;
        }
        if (password !== confirm) {
            showAlert('step4', 'As senhas não coincidem.', 'error');
            return;
        }

        btn.disabled = true;
        spinner.classList.add('show');
        hideAlert('step4');

        const formData = new FormData();
        formData.append('action', 'create_admin');
        formData.append('username', username);
        formData.append('email', email);
        formData.append('password', password);
        formData.append('password_confirm', confirm);

        try {
            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const data = await resp.json();

            if (data.success) {
                stepCompleted[4] = true;
                showAlert('step4', `Administrador "${data.username}" criado com sucesso!`, 'success');
                setTimeout(() => goToStep(5), 1500);
            } else {
                showAlert('step4', data.message, 'error');
            }
        } catch (err) {
            showAlert('step4', 'Erro de comunicação: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            spinner.classList.remove('show');
        }
    }

    // ===== STEP 5: FINALIZE =====
    async function runFinalize() {
        const checklist = document.getElementById('final-checklist');
        const buttons = document.getElementById('final-buttons');
        const badge = document.getElementById('final-badge');
        const title = document.getElementById('final-title');
        const subtitle = document.getElementById('final-subtitle');

        checklist.innerHTML = '';
        buttons.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'finalize');

            const resp = await fetch(API_URL, { method: 'POST', body: formData });
            const data = await resp.json();

            const statusIcons = { ok: '✓', warning: '⚠', error: '✗' };
            const statusClass = { ok: 'fi-ok', warning: 'fi-warning', error: 'fi-error' };

            data.report.forEach((item, i) => {
                const el = document.createElement('div');
                el.className = `final-item ${statusClass[item.status]}`;
                el.innerHTML = `
                    <div class="fi-icon">${statusIcons[item.status]}</div>
                    <div class="fi-text">
                        <div class="fi-label">${item.label}</div>
                        <div class="fi-detail">${item.detail}</div>
                    </div>
                `;
                checklist.appendChild(el);

                // Animação escalonada
                setTimeout(() => el.classList.add('visible'), 200 * (i + 1));
            });

            // Após todas as animações, mostrar resultado
            setTimeout(() => {
                if (data.success) {
                    badge.textContent = '✓';
                    badge.style.borderColor = 'var(--accent-green)';
                    title.textContent = 'Instalação Concluída!';
                    subtitle.textContent = 'Seu Unbound Dashboard está pronto para uso.';
                } else {
                    badge.textContent = '⚠';
                    badge.style.borderColor = 'var(--accent-amber)';
                    badge.style.background = 'var(--accent-amber-glow)';
                    title.textContent = 'Instalação Concluída com Avisos';
                    subtitle.textContent = 'Alguns componentes opcionais precisam de atenção.';
                }
                buttons.style.display = 'flex';
                stepCompleted[5] = true;
            }, 200 * (data.report.length + 1));

        } catch (err) {
            showAlert('step5', 'Erro na finalização: ' + err.message, 'error');
        }
    }

    // ===== PASSWORD STRENGTH =====
    function updatePasswordStrength() {
        const password = document.getElementById('admin_password').value;
        const bar = document.getElementById('password-bar');
        
        bar.className = 'password-strength-bar';
        
        if (password.length === 0) { bar.style.width = '0'; return; }
        
        let score = 0;
        if (password.length >= 6) score++;
        if (password.length >= 10) score++;
        if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        const classes = ['', 'strength-weak', 'strength-fair', 'strength-good', 'strength-strong', 'strength-strong'];
        bar.classList.add(classes[score] || 'strength-weak');
    }

    // ===== HELPERS =====
    function showAlert(step, message, type) {
        const el = document.getElementById('alert-' + step);
        el.className = `alert alert-${type} show`;
        el.textContent = message;
    }
    function hideAlert(step) {
        const el = document.getElementById('alert-' + step);
        el.className = 'alert';
        el.textContent = '';
    }

    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', () => {
        runEnvCheck();
    });
    </script>
</body>
</html>