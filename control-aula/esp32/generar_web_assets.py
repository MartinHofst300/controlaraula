#!/usr/bin/env python3
"""
Generador de assets web comprimidos (GZIP -> PROGMEM) para ESP32 Standalone.
Toma los estilos, lógica e íconos reales de hosting/public/ y genera
control_aula/web_assets.h 100% autocontenido (sin CDN ni Internet).
"""
import gzip
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent.parent
HOSTING_PUB = ROOT / "hosting" / "public"
OUT_HEADER = pathlib.Path(__file__).resolve().parent / "control_aula" / "web_assets.h"

# ---------------------------------------------------------------------------
# 1. CSS BASE COMPATIBLE CON BOOTSTRAP 5.3 (100% OFFLINE, LIGHT & DARK)
# ---------------------------------------------------------------------------
BOOTSTRAP_OFFLINE_CSS = r"""
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font-sans);color:var(--text-primary);background-color:var(--bg-base)}
h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{margin-top:0;margin-bottom:.5rem;font-weight:600;line-height:1.25;color:inherit}
.h5,h5{font-size:1.15rem}.h6,h6{font-size:1rem}
p{margin-top:0;margin-bottom:.5rem}
small,.small{font-size:.84em}
a{color:var(--color-primary);text-decoration:none}
a:hover{text-decoration:underline}
button,input,select,textarea{font-family:inherit;font-size:inherit;line-height:inherit;margin:0}
button{cursor:pointer}
.bi-svg{display:inline-block;width:1em;height:1em;vertical-align:-0.14em;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.bi-svg.fill-ico{fill:currentColor;stroke:none}
.fs-2{font-size:1.75rem!important}.fs-4{font-size:1.35rem!important}.fs-5{font-size:1.15rem!important}
.fw-medium{font-weight:500!important}.fw-semibold{font-weight:600!important}.fw-bold{font-weight:700!important}
.opacity-75{opacity:.75!important}
.text-center{text-align:center!important}.text-end{text-align:right!important}
.text-white{color:#fff!important}
.text-primary{color:var(--color-primary)!important}
.text-secondary,.text-body-secondary{color:var(--text-secondary)!important}
.text-success{color:var(--color-success)!important}
.text-danger{color:var(--color-danger)!important}
.bg-body{background-color:var(--bg-surface)!important}
.bg-body-tertiary{background-color:var(--bg-base)!important}
.bg-primary{background-color:var(--color-primary)!important}
.border{border:1px solid var(--border-subtle)!important}
.border-0{border:0!important}
.border-top{border-top:1px solid var(--border-subtle)!important}
.border-primary{border-color:var(--color-primary)!important}
.border-2{border-width:2px!important}
.rounded-2{border-radius:.375rem!important}.rounded-3{border-radius:.5rem!important}.rounded-pill{border-radius:50rem!important}
.shadow-sm{box-shadow:0 .125rem .35rem rgba(0,0,0,.08)!important}
.shadow{box-shadow:0 .5rem 1.5rem rgba(0,0,0,.2)!important}
.container{width:100%;margin-left:auto;margin-right:auto;padding-left:12px;padding-right:12px}
.w-100{width:100%!important}.min-vh-100{min-height:100vh!important}
.d-block{display:block!important}.d-flex{display:flex!important}.d-inline-flex{display:inline-flex!important}
.flex-column{flex-direction:column!important}.flex-wrap{flex-wrap:wrap!important}
.flex-grow-1{flex-grow:1!important}.flex-shrink-0{flex-shrink:0!important}
.align-items-center{align-items:center!important}.align-items-start{align-items:flex-start!important}.align-items-end{align-items:flex-end!important}.align-middle{vertical-align:middle!important}
.justify-content-center{justify-content:center!important}.justify-content-between{justify-content:space-between!important}
.gap-1{gap:.25rem!important}.gap-2{gap:.5rem!important}
.m-0{margin:0!important}.mb-0{margin-bottom:0!important}.mb-1{margin-bottom:.25rem!important}.mb-2{margin-bottom:.5rem!important}.mb-3{margin-bottom:1rem!important}.mb-4{margin-bottom:1.5rem!important}
.mt-2{margin-top:.5rem!important}.mt-3{margin-top:1rem!important}.mt-4{margin-top:1.5rem!important}.mt-auto{margin-top:auto!important}
.me-1{margin-right:.25rem!important}.me-2{margin-right:.5rem!important}.my-1{margin-top:.25rem!important;margin-bottom:.25rem!important}
.p-3{padding:1rem!important}.p-4{padding:1.5rem!important}
.px-2{padding-left:.5rem!important;padding-right:.5rem!important}.px-3{padding-left:.9rem!important;padding-right:.9rem!important}
.py-1{padding-top:.25rem!important;padding-bottom:.25rem!important}.py-2{padding-top:.5rem!important;padding-bottom:.5rem!important}.py-3{padding-top:1rem!important;padding-bottom:1rem!important}.py-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}
.pt-1{padding-top:.25rem!important}.pt-2{padding-top:.5rem!important}.pt-3{padding-top:1rem!important}
.card{background-color:var(--bg-card);border:1px solid var(--border-subtle);border-radius:var(--radius-md);position:relative;display:flex;flex-direction:column;min-width:0;word-wrap:break-word}
.card-header{padding:.75rem 1rem;border-bottom:1px solid var(--border-subtle);border-radius:calc(var(--radius-md) - 1px) calc(var(--radius-md) - 1px) 0 0}
.card-body{flex:1 1 auto;padding:1.25rem}
.card-footer{padding:.6rem 1rem;border-top:1px solid var(--border-subtle);border-radius:0 0 calc(var(--radius-md) - 1px) calc(var(--radius-md) - 1px)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:.3em .6em;font-size:.75em;font-weight:700;line-height:1;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.375rem}
.text-bg-primary{color:#fff!important;background-color:var(--color-primary)!important}
.text-bg-secondary{color:#fff!important;background-color:#6c757d!important}
.text-bg-success{color:#fff!important;background-color:#198754!important}
.text-bg-danger{color:#fff!important;background-color:#dc3545!important}
.text-bg-warning{color:#111!important;background-color:#ffc107!important}
.text-bg-info{color:#052c65!important;background-color:#0dcaf0!important}
.text-bg-light{color:var(--text-primary)!important;background-color:var(--bg-subtle)!important}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:.45rem .85rem;font-size:.9rem;font-weight:500;line-height:1.4;color:var(--text-primary);text-align:center;text-decoration:none;vertical-align:middle;cursor:pointer;user-select:none;background-color:transparent;border:1px solid transparent;border-radius:.375rem;transition:color .15s,background-color .15s,border-color .15s,opacity .15s}
.btn:hover{text-decoration:none}
.btn:disabled{opacity:.6;pointer-events:none}
.btn-sm{padding:.28rem .6rem;font-size:.82rem;border-radius:.3rem}
.btn-primary{color:#fff;background-color:var(--color-primary);border-color:var(--color-primary)}
.btn-primary:hover{background-color:var(--color-primary-hover);border-color:var(--color-primary-hover);color:#fff}
.btn-success{color:#fff;background-color:#198754;border-color:#198754}
.btn-success:hover{background-color:#157347;border-color:#146c43;color:#fff}
.btn-danger{color:#fff;background-color:#dc3545;border-color:#dc3545}
.btn-danger:hover{background-color:#bb2d3b;border-color:#b02a37;color:#fff}
.btn-outline-primary{color:var(--color-primary);border-color:var(--color-primary)}
.btn-outline-primary:hover{color:#fff;background-color:var(--color-primary);border-color:var(--color-primary)}
.btn-outline-secondary{color:var(--text-secondary);border-color:var(--border-subtle)}
.btn-outline-secondary:hover,.btn-outline-secondary.active{color:#fff;background-color:#6c757d;border-color:#6c757d}
.btn-outline-success{color:#198754;border-color:#198754}
.btn-outline-success:hover{color:#fff;background-color:#198754;border-color:#198754}
.btn-outline-danger{color:#dc3545;border-color:#dc3545}
.btn-outline-danger:hover{color:#fff;background-color:#dc3545;border-color:#dc3545}
.btn-outline-warning{color:#b45309;border-color:#d97706}
.btn-outline-warning:hover{color:#000;background-color:#ffc107;border-color:#ffc107}
html[data-theme="oscuro"] .btn-outline-warning{color:#ffc107;border-color:#ffc107}
.btn-outline-info{color:#0aa2c0;border-color:#0dcaf0}
.btn-outline-info:hover{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}
.btn-group{display:inline-flex;vertical-align:middle}
.btn-group>.btn{border-radius:0;margin-left:-1px}
.btn-group>.btn:first-child{margin-left:0;border-top-left-radius:.3rem;border-bottom-left-radius:.3rem}
.btn-group>.btn:last-child{border-top-right-radius:.3rem;border-bottom-right-radius:.3rem}
.btn-close{width:28px;height:28px;padding:0;background:transparent;border:0;color:var(--text-secondary);font-size:20px;line-height:1;display:inline-flex;align-items:center;justify-content:center;border-radius:4px}
.btn-close::before{content:"\00d7"}
.btn-close:hover{background-color:var(--bg-subtle);color:var(--text-primary)}
.form-label{display:inline-block;margin-bottom:.3rem;font-size:.85rem;font-weight:500}
.form-control,.form-select{display:block;width:100%;padding:.42rem .7rem;font-size:.9rem;line-height:1.45;color:var(--text-primary);background-color:var(--bg-input);border:1px solid var(--border-subtle);border-radius:.375rem;outline:none;transition:border-color .15s,box-shadow .15s}
.form-control:focus,.form-select:focus{border-color:var(--color-primary);box-shadow:0 0 0 .2rem var(--color-primary-glow)}
.form-control-sm,.form-select-sm{padding:.26rem .55rem;font-size:.84rem;border-radius:.3rem}
.form-control-color{width:44px;height:32px;padding:2px;cursor:pointer}
.form-range{width:100%;height:1.2rem;accent-color:var(--color-primary);cursor:pointer}
.form-check-input{width:1.15em;height:1.15em;accent-color:var(--color-primary);cursor:pointer}
.input-group{position:relative;display:flex;flex-wrap:nowrap;align-items:stretch;width:100%}
.input-group>.form-control,.input-group>.form-select{position:relative;flex:1 1 auto;width:1%;min-width:0}
.input-group-text{display:flex;align-items:center;padding:.4rem .65rem;font-size:.88rem;color:var(--text-secondary);background-color:var(--bg-subtle);border:1px solid var(--border-subtle);border-radius:.375rem 0 0 .375rem;white-space:nowrap}
.input-group-sm>.input-group-text,.input-group-sm>.form-control,.input-group-sm>.form-select{padding:.26rem .55rem;font-size:.84rem}
.input-group>:not(:first-child){margin-left:-1px;border-top-left-radius:0;border-bottom-left-radius:0}
.input-group>:not(:last-child){border-top-right-radius:0;border-bottom-right-radius:0}
.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}
.table{width:100%;margin-bottom:0;color:var(--text-primary);border-collapse:collapse;font-size:.9rem}
.table th,.table td{padding:.65rem .75rem;border-bottom:1px solid var(--border-subtle);text-align:left}
.table-hover tbody tr:hover{background-color:var(--bg-subtle)}
.nav{display:flex;flex-wrap:wrap;padding-left:0;margin-bottom:0;list-style:none}
.alert{position:relative;padding:.65rem .9rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.375rem}
.alert-danger{color:var(--text-danger-alert);background-color:var(--color-danger-bg);border-color:var(--color-danger)}
.esp32-local-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:50rem;font-size:11px;font-weight:700;background:rgba(13,110,253,.12);color:var(--color-primary);border:1px solid var(--border-highlight)}
"""

# ---------------------------------------------------------------------------
# 2. ICONOS SVG INLINE (TODOS LOS 62 ICONOS BI-* USADOS, 0% CDN)
# ---------------------------------------------------------------------------
ICONS_JS = r"""
(function(){
const P={
'book':'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
'box-arrow-in-right':'<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
'box-arrow-right':'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
'briefcase':'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
'building':'<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
'building-gear':'<rect x="3" y="3" width="14" height="18" rx="2"/><path d="M7 7h2m-2 4h2m-2 4h2m4-8h1m-1 4h1"/><circle cx="18" cy="17" r="3"/>',
'check-circle-fill':'<circle cx="12" cy="12" r="10"/><polyline points="8 12 11 15 16 9"/>',
'check2':'<polyline points="20 6 9 17 4 12"/>',
'check2-circle':'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
'circle-half':'<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
'clock':'<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
'clock-fill':'<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
'clock-history':'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><polyline points="12 7 12 12 15 14"/>',
'cpu':'<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3m6-3v3M9 19v3m6-3v3M2 9h3m-3 6h3M19 9h3m-3 6h3"/>',
'door-open':'<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h20"/><path d="M13 20V3L6 5v15"/><circle cx="10" cy="12" r=".8"/>',
'easel2':'<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v5m-5 0 3-5m7 5-3-5M12 2v2"/>',
'exclamation-triangle-fill':'<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
'eye-slash':'<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>',
'file-earmark-spreadsheet':'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="12" y1="11" x2="12" y2="19"/>',
'film':'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 4v16M17 4v16M2 8h5m10 0h5M2 12h20M2 16h5m10 0h5"/>',
'gear':'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.08a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.08a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
'hdd-network':'<rect x="3" y="4" width="18" height="8" rx="2"/><circle cx="7" cy="8" r="1"/><path d="M12 12v4M6 20h12M12 16v4"/>',
'hdmi':'<path d="M4 9h16v4l-2 2H6l-2-2V9z"/><line x1="8" y1="12" x2="16" y2="12"/>',
'house-door':'<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
'journal-text':'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
'key':'<circle cx="8" cy="14" r="4"/><path d="M11 11l9-9m-3 3 2 2m-4-1 2 2"/>',
'lightbulb':'<path d="M9 18h6m-5 3h4M12 2a7 7 0 0 0-4 12.7V16a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-1.3A7 7 0 0 0 12 2z"/>',
'lightbulb-fill':'<path d="M9 18h6m-5 3h4"/><path d="M12 2a7 7 0 0 0-4 12.7V16a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-1.3A7 7 0 0 0 12 2z" fill="currentColor"/>',
'lightbulb-off':'<path d="M9 18h6m-5 3h4M12 2a7 7 0 0 1 5.6 11.2M7.5 13.5A7 7 0 0 1 6 7"/><line x1="2" y1="2" x2="22" y2="22"/>',
'lock':'<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
'moon-stars':'<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
'mortarboard':'<polygon points="12 3 2 8 12 13 22 8 12 3"/><path d="M6 10.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.5"/>',
'palette':'<circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="8" r="1" fill="currentColor"/><circle cx="15" cy="10" r="1" fill="currentColor"/><circle cx="15" cy="14" r="1" fill="currentColor"/>',
'pause-circle':'<circle cx="12" cy="12" r="9"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/>',
'pc-display':'<rect x="2" y="4" width="14" height="11" rx="1"/><path d="M6 19h6m-3-4v4"/><rect x="18" y="4" width="4" height="15" rx="1"/>',
'pc-display-horizontal':'<rect x="2" y="4" width="20" height="12" rx="2"/><path d="M8 20h8m-4-4v4"/>',
'pencil':'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
'people':'<circle cx="9" cy="7" r="3"/><path d="M3 20v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/><circle cx="17" cy="8" r="2.5"/><path d="M17 14a4 4 0 0 1 4 4v2"/>',
'person':'<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/>',
'person-badge':'<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8 18a4 4 0 0 1 8 0"/>',
'person-check':'<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a5 5 0 0 1 5-5h2"/><polyline points="15 16 17 18 21 14"/>',
'person-gear':'<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a5 5 0 0 1 5-5h2"/><circle cx="18" cy="17" r="2.5"/>',
'play-fill':'<polygon points="6 4 20 12 6 20 6 4" fill="currentColor"/>',
'plus-circle':'<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
'power':'<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
'projector':'<rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="16" cy="12" r="2.5"/><path d="M6 12h4M6 17v2m12-2v2"/>',
'projector-fill':'<rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="16" cy="12" r="2.5" fill="currentColor"/><path d="M6 12h4M6 17v2m12-2v2"/>',
'search':'<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
'shield-check':'<path d="M12 2 4 5v6c0 5.5 3.4 10 8 11 4.6-1 8-5.5 8-11V5l-8-3z"/><polyline points="9 12 11 14 15 10"/>',
'shield-lock':'<path d="M12 2 4 5v6c0 5.5 3.4 10 8 11 4.6-1 8-5.5 8-11V5l-8-3z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10.5 11V9.5a1.5 1.5 0 0 1 3 0V11"/>',
'sliders':'<line x1="4" y1="6" x2="20" y2="6"/><circle cx="9" cy="6" r="2"/><line x1="4" y1="12" x2="20" y2="12"/><circle cx="15" cy="12" r="2"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="11" cy="18" r="2"/>',
'snow2':'<path d="M12 2v20M2 12h20M4.9 4.9l14.2 14.2M19.1 4.9 4.9 19.1"/>',
'stop-circle':'<circle cx="12" cy="12" r="9"/><rect x="9" y="9" width="6" height="6"/>',
'sun':'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>',
'thermometer-half':'<path d="M14 14.76V5a2 2 0 0 0-4 0v9.76a4 4 0 1 0 4 0z"/>',
'thermometer-sun':'<path d="M14 14.76V5a2 2 0 0 0-4 0v9.76a4 4 0 1 0 4 0z"/><path d="M18 6h2M18 10h2"/>',
'tools':'<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
'trash':'<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>',
'volume-mute-fill':'<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>',
'volume-up-fill':'<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.5 8.5a5 5 0 0 1 0 7m3-10a9 9 0 0 1 0 13"/>',
'wifi':'<path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/>',
'wind':'<path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/>'
};
function r(root){
  (root||document).querySelectorAll('i.bi').forEach(el=>{
    if(el.dataset.biDone)return;
    const c=Array.from(el.classList).find(x=>x.startsWith('bi-')&&x!=='bi');
    const k=c?c.slice(3):'';
    el.dataset.biDone='1';
    el.innerHTML='<svg class="bi-svg" viewBox="0 0 24 24" aria-hidden="true">'+(P[k]||P['building'])+'</svg>';
  });
}
window.__renderBiIcons=r;
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',()=>r(document));}else{r(document);}
new MutationObserver(muts=>{muts.forEach(m=>m.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches&&n.matches('i.bi'))r(n.parentNode);else r(n);}}));}).observe(document.documentElement,{childList:true,subtree:true});
})();
"""

# ---------------------------------------------------------------------------
# 3. LOGIN HTML (100% OFFLINE, IDENTICO A login.php)
# ---------------------------------------------------------------------------
LOGIN_HTML = r"""<!DOCTYPE html>
<html lang="es" data-bs-theme="light" data-theme="claro" data-accent="azul" data-font="sans" data-density="comoda" data-contrast="normal" data-motion="normal">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="theme-color" content="#0d6efd">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Control Aula">
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<title>Iniciar Sesión | Control de Aula</title>
<script>
(function(){try{var p=JSON.parse(localStorage.getItem('control_aula_prefs_bs5')||'{}'),r=document.documentElement,t=p.tema||'claro';if(t==='auto')t=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)?'claro':'oscuro';r.setAttribute('data-theme',t);r.setAttribute('data-bs-theme',t==='claro'?'light':'dark');r.setAttribute('data-accent',p.acento||'azul');if(p.fuente)r.setAttribute('data-font',p.fuente);if(p.densidad)r.setAttribute('data-density',p.densidad);if(p.altoContraste)r.setAttribute('data-contrast','alto');if(p.reducirAnimaciones)r.setAttribute('data-motion','reducido');if(p.escalaPct){var pct=Math.max(85,Math.min(130,parseInt(p.escalaPct,10)||100));r.style.setProperty('--ui-scale',(pct/100).toFixed(2));}if(p.acento==='custom'&&p.colorCustom){r.style.setProperty('--color-primary',p.colorCustom);r.style.setProperty('--color-primary-hover',p.colorCustom);r.style.setProperty('--text-gold',p.colorCustom);}}catch(e){}})();
</script>
<link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body class="login-body bg-body-tertiary d-flex align-items-center py-4 min-vh-100">
<div class="container login-wrapper" style="max-width:420px;">
<div class="card shadow-sm border login-card">
<div class="card-header bg-primary text-white text-center py-3 border-0">
<i class="bi bi-building-gear fs-2 d-block mb-1"></i>
<h1 class="h5 fw-bold mb-0">Sistema de Control de Aula</h1>
<small class="opacity-75">Climatización, Iluminación y Proyección · ESP32 Local</small>
</div>
<div class="card-body p-4">
<div id="loginError" class="alert alert-danger d-flex align-items-center py-2 px-3 small mb-3" role="alert" style="display:none!important;">
<i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
<div id="loginErrorTxt"></div>
</div>
<form id="loginForm" method="POST" action="login.php" class="login-form">
<div class="mb-3">
<label for="usuario" class="form-label fw-semibold small mb-1">Usuario</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person"></i></span>
<input type="text" class="form-control" id="usuario" name="usuario" required autocomplete="username" autofocus placeholder="Ej: aula101 o admin">
</div>
</div>
<div class="mb-4">
<label for="password" class="form-label fw-semibold small mb-1">Contraseña</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-lock"></i></span>
<input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
</div>
</div>
<button type="submit" id="btnLoginSubmit" class="btn btn-primary w-100 py-2 fw-semibold">
<i class="bi bi-box-arrow-in-right me-1"></i>
<span>Ingresar al Aula</span>
</button>
<button type="button" id="btnInstalarPwa" class="btn btn-outline-primary w-100 py-2 fw-semibold mt-2" style="display:none;">
<i class="bi bi-plus-circle me-1"></i>
<span>Instalar App en el Celular</span>
</button>
<a id="btnDescargarApk" href="/ControlAula.apk" download="ControlAula.apk" class="btn btn-outline-success w-100 py-2 fw-semibold mt-2">
<i class="bi bi-hdd-network me-1"></i>
<span>Descargar App Android (.apk)</span>
</a>
</form>
</div>
<div class="card-footer bg-body-tertiary text-center py-2">
<small class="text-body-secondary"><i class="bi bi-shield-lock me-1"></i>Acceso exclusivo para docentes y administradores</small>
</div>
</div>
</div>
<script>""" + ICONS_JS + r"""
(function(){
if(window.AndroidApp){const a=document.getElementById('btnDescargarApk');if(a)a.style.display='none';}
if('serviceWorker' in navigator){navigator.serviceWorker.register('/sw.js',{scope:'/'}).catch(()=>{});}
let deferredPwa=null;const btnPwa=document.getElementById('btnInstalarPwa');
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredPwa=e;if(btnPwa)btnPwa.style.display='';});
window.addEventListener('appinstalled',()=>{deferredPwa=null;if(btnPwa)btnPwa.style.display='none';});
if(btnPwa){btnPwa.addEventListener('click',async()=>{if(!deferredPwa)return;deferredPwa.prompt();await deferredPwa.userChoice;deferredPwa=null;btnPwa.style.display='none';});}
const f=document.getElementById('loginForm'),err=document.getElementById('loginError'),errT=document.getElementById('loginErrorTxt'),btn=document.getElementById('btnLoginSubmit');
const qs=new URLSearchParams(location.search);
if(qs.get('err')){errT.textContent=qs.get('err');err.style.removeProperty('display');}
f.addEventListener('submit',async function(e){
  e.preventDefault();
  err.setAttribute('style','display:none!important;');
  btn.disabled=true;
  try{
    const res=await fetch('login.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({usuario:document.getElementById('usuario').value.trim(),password:document.getElementById('password').value,client_ts:Math.floor(Date.now()/1000)})});
    const d=await res.json();
    if(d&&d.ok){location.href='index.php';return;}
    errT.textContent=(d&&d.error)||'Credenciales incorrectas. Verifique usuario y contraseña.';
    err.style.removeProperty('display');
  }catch(ex){
    errT.textContent='No se pudo conectar con el ESP32.';
    err.style.removeProperty('display');
  }finally{btn.disabled=false;}
});
})();
</script>
</body>
</html>
"""

# ---------------------------------------------------------------------------
# 4. CONSTRUIR index.html A PARTIR DE hosting/public/index.php
# ---------------------------------------------------------------------------
def build_index_html() -> str:
    raw = (HOSTING_PUB / "index.php").read_text(encoding="utf-8")
    # Quitar bloque PHP inicial
    html = re.sub(r"^<\?php.*?\?>\s*", "", raw, count=1, flags=re.S)
    # Quitar CDN Bootstrap CSS y Bootstrap Icons y Bootstrap JS y pwa.js
    html = re.sub(r"\s*<!-- Bootstrap 5\.3 CSS.*?-->", "", html)
    html = re.sub(r'\s*<link[^>]*cdn\.jsdelivr\.net[^>]*>', "", html)
    html = re.sub(r'\s*<script[^>]*cdn\.jsdelivr\.net[^>]*></script>', "", html)
    html = re.sub(r'\s*<script[^>]*pwa\.js[^>]*></script>', "", html)
    # Quitar query strings de cache-busting innecesarios
    html = html.replace('estilos.css?v=20261126b', 'estilos.css')
    html = html.replace('panel.js?v=20261126b', 'panel.js')
    # Reemplazar metas dinámicas PHP por placeholders que llena panel.js vía /api/sesion.php
    html = re.sub(
        r'<meta name="csrf-token".*?(?=<title>)',
        '<meta name="csrf-token" content="">\n'
        '<meta name="user-role" content="aula">\n'
        '<meta name="user-id" content="0">\n'
        '<meta name="user-aula-id" content="1">\n',
        html,
        flags=re.S,
    )
    # Reemplazar bloque script inicial que inyectaba PHP
    html = re.sub(
        r'window\.PREFS_SERVIDOR_DB\s*=.*?\n\s*window\.AULA_SESION_ID\s*=.*?\n\s*window\.USER_ROL\s*=.*?\n',
        'window.PREFS_SERVIDOR_DB = null;\nwindow.AULA_SESION_ID = 1;\nwindow.USER_ROL = "aula";\n',
        html,
    )
    # Reemplazar cabecera condicional admin/aula por ambos elementos ocultos inicialmente (los muestra /api/sesion.php)
    header_cond = (
        '<div id="selectorAulaWrap" class="selector-aula-contenedor input-group input-group-sm" style="width:auto;display:none;">'
        '<label class="input-group-text fw-semibold" for="selectAulaHeader">'
        '<i class="bi bi-door-open me-1 text-primary"></i>Espacio:</label>'
        '<select id="selectAulaHeader" class="form-select form-select-sm select-aula">'
        '<option value="1">Cargando espacios...</option></select></div>'
        '<div id="espacioFijoBadge" class="espacio-fijo-badge badge text-bg-light border px-3 py-2 align-items-center gap-2" style="display:none;" title="Espacio físico asignado a esta terminal">'
        '<i id="espacioFijoIcono" class="bi bi-house-door text-primary"></i>'
        '<span id="espacioFijoNombre" class="fw-semibold">Aula 101</span></div>'
    )
    html = re.sub(
        r'<\?php if \(\$esAdmin\): \?>.*?(?=\s*<div id="badgeConexion")',
        header_cond + '\n',
        html,
        count=1,
        flags=re.S,
    )
    # Ocultar panel admin hasta verificar sesión admin
    html = html.replace('<?php if ($esAdmin): ?>', '')
    html = html.replace('<?php endif; ?>', '')
    html = html.replace(
        '<div class="admin-panel-wrapper" id="adminPanelWrapper">',
        '<div class="admin-panel-wrapper" id="adminPanelWrapper" style="display:none;">',
    )
    # Reemplazar datos PHP del modal Mi Perfil por spans con IDs
    html = re.sub(
        r'<strong>Usuario: <\?php echo.*?\?></strong>',
        '<strong>Usuario: <span id="perfilUsuarioTxt">--</span></strong>',
        html,
    )
    html = re.sub(
        r'<small>Rol asignado: <\?php echo.*?\?></small>',
        '<small>Rol asignado: <span id="perfilRolTxt">--</span></small>',
        html,
    )
    html = re.sub(
        r'<span class="rol-tag badge text-bg-primary"><\?php echo.*?\?></span>',
        '<span id="perfilRolBadge" class="rol-tag badge text-bg-primary">--</span>',
        html,
    )
    html = re.sub(
        r'value="<\?php echo htmlspecialchars\(\$nombreCompleto.*?\?>" required maxlength="100"',
        'value="" required maxlength="100"',
        html,
    )
    # Agregar botones Instalar PWA y Descargar APK (se ocultan si ya esta dentro del APK)
    html = html.replace(
        '<button type="button" id="btnAbrirConfig"',
        '<button type="button" id="btnInstalarPwa" class="btn btn-success btn-sm px-3 fw-medium" style="display:none;" title="Instalar como aplicación">'
        '<i class="bi bi-plus-circle"></i><span>Instalar App</span></button>\n'
        '<a id="btnDescargarApk" href="/ControlAula.apk" download="ControlAula.apk" class="btn btn-outline-success btn-sm px-3 fw-medium" title="Descargar app Android (.apk)">'
        '<i class="bi bi-hdd-network"></i><span>App Android</span></a>\n'
        '<button type="button" id="btnAbrirConfig"',
    )
    # Asegurar que no quede ningun tag PHP suelto
    assert "<?php" not in html and "?>" not in html, "Quedaron tags PHP sin reemplazar en index.html!"
    return html

# ---------------------------------------------------------------------------
# 5. ADAPTAR panel.js PARA INICIALIZAR SESIÓN LOCAL Y RENDERIZAR ICONOS OFFLINE
# ---------------------------------------------------------------------------
def build_panel_js() -> str:
    orig_js = (HOSTING_PUB / "assets" / "js" / "panel.js").read_text(encoding="utf-8")
    # Envolver el IIFE original dentro de una funcion __iniciarPanelControl()
    # que se dispara despues de consultar /api/sesion.php
    assert orig_js.lstrip().startswith("/**") or orig_js.lstrip().startswith("(function")
    idx_iife = orig_js.index("(function () {")
    end_iife = orig_js.rindex("})();")
    inner_body = orig_js[idx_iife + len("(function () {"):end_iife]

    bootstrap_wrapper = (
        ICONS_JS
        + r"""
(function(){
  if (window.AndroidApp) {
    const a = document.getElementById('btnDescargarApk');
    if (a) a.style.display = 'none';
  }
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
  }
  let deferredPwa = null;
  const btnPwa = document.getElementById('btnInstalarPwa');
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPwa = e;
    if (btnPwa) btnPwa.style.display = '';
  });
  window.addEventListener('appinstalled', () => {
    deferredPwa = null;
    if (btnPwa) btnPwa.style.display = 'none';
  });
  if (btnPwa) {
    btnPwa.addEventListener('click', async () => {
      if (!deferredPwa) return;
      deferredPwa.prompt();
      await deferredPwa.userChoice;
      deferredPwa = null;
      btnPwa.style.display = 'none';
    });
  }
})();
(async function(){
  'use strict';
  try {
    const res = await fetch('api/sesion.php?client_ts=' + Math.floor(Date.now()/1000), {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (res.status === 401) {
      window.location.href = 'login.php';
      return;
    }
    const ses = await res.json();
    if (!ses || !ses.ok) {
      window.location.href = 'login.php';
      return;
    }
    const setMeta = (n, v) => {
      const el = document.querySelector('meta[name="' + n + '"]');
      if (el) el.setAttribute('content', String(v ?? ''));
    };
    setMeta('csrf-token', ses.csrf_token || '');
    setMeta('user-role', ses.rol || 'aula');
    setMeta('user-id', ses.usuario_id || 1);
    setMeta('user-aula-id', ses.aula_id || 1);
    window.USER_ROL = ses.rol || 'aula';
    window.AULA_SESION_ID = ses.aula_id || 1;
    window.PREFS_SERVIDOR_DB = ses.preferencias || null;

    const esAdminSes = (ses.rol === 'admin');
    const selWrap = document.getElementById('selectorAulaWrap');
    const fijaBadge = document.getElementById('espacioFijoBadge');
    const adminWrap = document.getElementById('adminPanelWrapper');
    if (esAdminSes) {
      if (selWrap) selWrap.style.display = '';
      if (fijaBadge) fijaBadge.style.display = 'none';
      if (adminWrap) adminWrap.style.display = '';
    } else {
      if (selWrap) selWrap.remove();
      if (adminWrap) adminWrap.remove();
      if (fijaBadge) {
        fijaBadge.style.display = 'inline-flex';
        const nom = document.getElementById('espacioFijoNombre');
        if (nom) nom.textContent = (ses.aula_nombre || 'Aula 101').replace(/\s*-\s*General/i, '').trim();
        const ico = document.getElementById('espacioFijoIcono');
        if (ico) {
          ico.removeAttribute('data-bi-done');
          const t = ses.aula_tipo || 'Aula';
          ico.className = 'bi ' + (t === 'Laboratorio' ? 'bi-cpu' : (t === 'Taller' ? 'bi-tools' : 'bi-house-door')) + ' text-primary';
        }
      }
    }
    const pUsr = document.getElementById('perfilUsuarioTxt');
    if (pUsr) pUsr.textContent = ses.usuario || '';
    const pRol = document.getElementById('perfilRolTxt');
    if (pRol) pRol.textContent = esAdminSes ? 'Administrador del Sistema' : 'Cuenta de Aula / Espacio';
    const pBad = document.getElementById('perfilRolBadge');
    if (pBad) pBad.textContent = esAdminSes ? 'Admin' : 'Espacio';
    const pNom = document.getElementById('inputPerfilNombre');
    if (pNom) pNom.value = ses.nombre_completo || ses.usuario || '';
    if (window.__renderBiIcons) window.__renderBiIcons(document);
  } catch (e) {
    console.error('Error iniciando sesion local:', e);
    return;
  }
"""
        + inner_body
        + "\n})();\n"
    )
    return bootstrap_wrapper

# ---------------------------------------------------------------------------
# 6. MINIFICADORES SEGUROS + EMPAQUETADO GZIP -> C HEADER
# ---------------------------------------------------------------------------
def minify_css(css: str) -> str:
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    css = re.sub(r"\s+", " ", css)
    css = re.sub(r"\s*([{}:;,>+~])\s*", r"\1", css)
    css = css.replace(";}", "}")
    return css.strip()

def minify_html(html: str) -> str:
    html = re.sub(r"<!--.*?-->", "", html, flags=re.S)
    lines = [ln.strip() for ln in html.splitlines() if ln.strip()]
    return "\n".join(lines)

def to_c_array(name: str, data: bytes, compress: bool = True):
    payload = gzip.compress(data, compresslevel=9) if compress else data
    parts = [f"0x{b:02x}" for b in payload]
    lines = []
    for i in range(0, len(parts), 16):
        lines.append("  " + ", ".join(parts[i:i+16]))
    body = ",\n".join(lines)
    tag = "GZIP" if compress else "RAW"
    return (
        f"// Original: {len(data)} bytes | {tag}: {len(payload)} bytes ({100.0*len(payload)/max(1,len(data)):.1f}%)\n"
        f"const uint32_t {name}_LEN = {len(payload)};\n"
        f"const uint8_t {name}[] PROGMEM = {{\n{body}\n}};\n"
    ), len(data), len(payload)

SW_JS = r"""const CACHE_NAME='control-aula-esp32-v3';
const STATIC_ASSETS=['/assets/css/estilos.css','/assets/js/panel.js','/assets/icons/icon-192.png','/assets/icons/icon-512.png','/assets/icons/icon.svg','/manifest.json'];
self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE_NAME).then(c=>c.addAll(STATIC_ASSETS)).catch(()=>{}));self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==CACHE_NAME).map(k=>caches.delete(k)))));self.clients.claim();});
self.addEventListener('fetch',e=>{
  const req=e.request;
  if(req.method!=='GET')return;
  const u=new URL(req.url);
  if(u.origin!==self.location.origin)return;
  if(u.pathname.startsWith('/api/')||u.pathname==='/login.php'||u.pathname==='/logout.php'||u.pathname==='/index.php'||u.pathname==='/')return;
  e.respondWith(fetch(req).then(r=>{if(r&&r.ok){const cl=r.clone();caches.open(CACHE_NAME).then(c=>c.put(req,cl));}return r;}).catch(()=>caches.match(req)));
});
"""

def main():
    estilos_orig = (HOSTING_PUB / "assets" / "css" / "estilos.css").read_text(encoding="utf-8")
    css_full = minify_css(BOOTSTRAP_OFFLINE_CSS + "\n" + estilos_orig).encode("utf-8")
    index_html = minify_html(build_index_html()).encode("utf-8")
    login_html = minify_html(LOGIN_HTML).encode("utf-8")
    panel_js = build_panel_js().encode("utf-8")
    sw_js = SW_JS.encode("utf-8")
    icon_svg = (HOSTING_PUB / "assets" / "icons" / "icon.svg").read_text(encoding="utf-8").strip().encode("utf-8")
    icon_192 = (HOSTING_PUB / "assets" / "icons" / "icon-192.png").read_bytes()
    icon_512 = (HOSTING_PUB / "assets" / "icons" / "icon-512.png").read_bytes()
    apk_path = pathlib.Path(__file__).resolve().parent.parent / "app_android" / "ControlAula.apk"
    apk_bytes = apk_path.read_bytes()
    manifest_json = (
        '{"id":"control-aula-esp32","name":"Control de Aula","short_name":"Control Aula",'
        '"description":"Control remoto local de iluminación, aire acondicionado y proyector desde el ESP32.",'
        '"lang":"es-AR","start_url":"/index.php","scope":"/","display":"standalone",'
        '"background_color":"#f8f9fa","theme_color":"#0d6efd",'
        '"icons":['
        '{"src":"/assets/icons/icon-192.png","sizes":"192x192","type":"image/png","purpose":"any"},'
        '{"src":"/assets/icons/icon-192.png","sizes":"192x192","type":"image/png","purpose":"maskable"},'
        '{"src":"/assets/icons/icon-512.png","sizes":"512x512","type":"image/png","purpose":"any"},'
        '{"src":"/assets/icons/icon-512.png","sizes":"512x512","type":"image/png","purpose":"maskable"},'
        '{"src":"/assets/icons/icon.svg","sizes":"any","type":"image/svg+xml","purpose":"any"}'
        ']}'
    ).encode("utf-8")

    assets = [
        ("ASSET_INDEX_HTML_GZ", index_html, True),
        ("ASSET_LOGIN_HTML_GZ", login_html, True),
        ("ASSET_ESTILOS_CSS_GZ", css_full, True),
        ("ASSET_PANEL_JS_GZ", panel_js, True),
        ("ASSET_SW_JS_GZ", sw_js, True),
        ("ASSET_ICON_SVG_GZ", icon_svg, True),
        ("ASSET_MANIFEST_JSON_GZ", manifest_json, True),
        ("ASSET_ICON_192_PNG", icon_192, False),
        ("ASSET_ICON_512_PNG", icon_512, False),
        ("ASSET_APP_APK", apk_bytes, False),
    ]

    blocks = [
        "#ifndef WEB_ASSETS_H",
        "#define WEB_ASSETS_H",
        "#include <Arduino.h>",
        "#include <pgmspace.h>",
        "// Generado automaticamente por generar_web_assets.py (100% offline, PWA instalable en PROGMEM)",
        "",
    ]
    total_orig = 0
    total_out = 0
    for name, raw, comp in assets:
        blk, o_len, g_len = to_c_array(name, raw, compress=comp)
        blocks.append(blk)
        total_orig += o_len
        total_out += g_len
        print(f"  {name:26s}: {o_len:6d} B -> {g_len:5d} B {'gzip' if comp else 'raw '}")

    blocks.append("#endif // WEB_ASSETS_H\n")
    OUT_HEADER.write_text("\n".join(blocks), encoding="utf-8")
    print(f"\nTOTAL WEB ASSETS: {total_orig:,} B -> {total_out:,} B ({total_out/1024:.1f} KB en Flash PROGMEM)")
    print(f"Escrito en: {OUT_HEADER}")

if __name__ == "__main__":
    main()
