<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&display=swap" rel="stylesheet">
<style>
    /* Thème Maison Filali pour l'administration */
    :root { --mf-cream:#fbf7f0; --mf-sable:#f3eadb; --mf-line:#ece6da; --mf-green:#0f4a3e; --mf-gold:#c6a15b; --mf-ink:#1f2a27; }
    .fi-body { background: var(--mf-cream) !important; }
    .fi-sidebar, .fi-sidebar-header { background:#fff !important; }
    .fi-sidebar { border-right:1px solid var(--mf-line); }
    .fi-sidebar-header { border-bottom:1px solid var(--mf-line); box-shadow:none !important; }
    .fi-topbar, .fi-topbar > nav { background:#fff !important; box-shadow:none !important; }
    .fi-topbar { border-bottom:1px solid var(--mf-line); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn, .fi-sidebar-item-btn:hover { background: var(--mf-sable) !important; }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label { color: var(--mf-green) !important; }
    .fi-header-heading, .fi-simple-header-heading, .fi-wi-stats-overview-stat-value, .mf-serif {
        font-family:'Cormorant Garamond', Georgia, serif !important; font-weight:600 !important; letter-spacing:0 !important;
    }
    .fi-header-heading { font-size:2.25rem !important; line-height:1.1 !important; color:var(--mf-ink); }
    .fi-wi-stats-overview-stat-value { font-size:2.6rem !important; color:var(--mf-green) !important; }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat { box-shadow:none !important; border:1px solid var(--mf-line) !important; --tw-ring-shadow:0 0 #0000 !important; }
    .fi-ta-header-cell { background: var(--mf-cream) !important; }
    .fi-ta-header-cell-label { text-transform:uppercase; letter-spacing:.12em; font-size:.7rem !important; font-weight:400 !important; color:#6e6a60 !important; }
    .fi-btn { letter-spacing:.06em; }
    .fi-btn.fi-color-primary:not(.fi-outlined) { text-transform:uppercase; font-size:.78rem; }

    /* Logo */
    .mf-brand { display:flex; align-items:center; gap:12px; }
    .mf-brand img { width:40px; height:40px; border-radius:50%; }
    .mf-brand strong { display:block; font-family:'Cormorant Garamond',serif; font-weight:600; font-size:18px; letter-spacing:.06em; color:var(--mf-ink); line-height:1.2; }
    .mf-brand small { display:block; font-size:10px; letter-spacing:.22em; text-transform:uppercase; color:var(--mf-gold); }
    .mf-view-site { font-size:.8rem; color:var(--mf-green); border-bottom:1px solid var(--mf-gold); margin-right:.75rem; white-space:nowrap; }

    /* Pastilles couleur */
    .mf-swatch { display:inline-block; width:14px; height:14px; border-radius:50%; vertical-align:-2px; margin-right:6px; box-shadow:0 0 0 2px #fff, 0 0 0 3px rgba(0,0,0,.12); }

    /* Page de connexion : panneau vert à gauche, formulaire à droite */
    .fi-simple-layout { background:#fff !important; }
    .mf-login-aside { display:none; }
    @media (min-width: 1024px) {
        .mf-login-aside { display:flex; position:fixed; inset:0 50% 0 0; background:var(--mf-green); flex-direction:column; align-items:center; justify-content:center; gap:40px; overflow:hidden; }
        .mf-login-aside .pattern { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:.12; }
        .mf-login-aside .frame { position:relative; width:min(420px, 60%); aspect-ratio: 420 / 600; border:1px solid var(--mf-gold); border-radius:999px 999px 0 0; padding:12px 12px 0; }
        .mf-login-aside .frame img { width:100%; height:100%; object-fit:cover; border-radius:999px 999px 0 0; }
        .mf-login-aside blockquote { position:relative; font-family:'Cormorant Garamond',serif; font-style:italic; font-size:32px; color:#f3eadb; }
        .fi-simple-layout .fi-simple-main-ctn { margin-left:50%; width:50%; }
    }
    .fi-simple-main { box-shadow:none !important; --tw-ring-shadow:0 0 #0000 !important; background:transparent !important; }
    .mf-login-note { margin-top:1.25rem; background:var(--mf-sable); padding:16px; text-align:center; }
    .mf-login-note strong { display:block; font-family:'Cormorant Garamond',serif; font-size:21px; font-weight:600; color:var(--mf-ink); }
    .mf-login-note span { display:block; font-size:13px; color:#6e6a60; margin-top:4px; }
    .fi-simple-header-heading { font-size:2.5rem !important; }
</style>
