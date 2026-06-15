body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    margin: 30px;
    color: #2d2d2d;
}

/* ── ENCABEZADO ── */
.header {
    width: 100%;
    border-bottom: 3px solid #1B1226;
    padding-bottom: 12px;
    margin-bottom: 20px;
}
.empresa {
    font-size: 20px;
    font-weight: bold;
    color: #1B1226;
}
.titulo {
    font-size: 13px;
    font-weight: bold;
    margin-top: 4px;
    color: #444;
    letter-spacing: 1px;
}
.subtitulo {
    font-size: 10px;
    margin-top: 4px;
    color: #888;
}
.meta {
    font-size: 9px;
    margin-top: 6px;
    color: #aaa;
}

/* ── SECCIÓN ── */
.seccion-titulo {
    font-size: 11px;
    font-weight: bold;
    margin-top: 24px;
    margin-bottom: 0px;
    padding: 6px 10px;
    background-color: #1B1226;
    color: #ffffff;
    letter-spacing: 0.5px;
}

/* ── TABLAS ── */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0px;
}
th, td {
    border: 1px solid #ddd;
    padding: 6px 8px;
}
th {
    background-color: #f0f0f0;
    font-size: 10px;
    color: #333;
    text-align: left;
}
tr:nth-child(even) td {
    background-color: #f9f9f9;
}

/* ── BADGES DE ESTADO ── */
.badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.3px;
}
.badge-pagada  { background: #e6f4ea; color: #2d7a3a; }
.badge-credito { background: #fff3e0; color: #9a6800; }
.badge-anulada { background: #fce8e8; color: #b71c1c; }

/* ── TFOOT ── */
tfoot td {
    border-top: none;
    border-left: 1px solid #ddd;
    border-right: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
    background-color: #fff;
    font-size: 11px;
    padding: 5px 8px;
}
.total-separador td {
    border: none;
    border-top: 2px solid #1B1226;
    padding: 0;
}
.total-fila td {
    color: #444;
}
.credito-fila td {
    color: #9a6800;
    font-style: italic;
}
.anulada-fila td {
    color: #b71c1c;
    font-style: italic;
}
.total-final td {
    background-color: #f0f0f0;
    font-size: 12px;
    border-top: 2px solid #1B1226;
    padding: 6px 8px;
}

/* ── COLORES UTILITARIOS ── */
.positivo { color: #1a7a3e; }
.negativo { color: #b03030; }
.alerta   { color: #9a6800; }
