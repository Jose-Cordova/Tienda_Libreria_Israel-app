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
.empresa   { font-size: 20px; font-weight: bold; color: #1B1226; }
.titulo    { font-size: 13px; font-weight: bold; margin-top: 4px; color: #444; letter-spacing: 1px; }
.subtitulo { font-size: 10px; margin-top: 4px; color: #888; }
.meta      { font-size: 9px; margin-top: 6px; color: #aaa; }

/* ── SECCIÓN ── */
.seccion-titulo {
    font-size: 11px; font-weight: bold;
    margin-top: 24px; margin-bottom: 0;
    padding: 6px 10px;
    background-color: #1B1226;
    color: #ffffff;
    letter-spacing: 0.5px;
}

/* ── TABLAS ── */
table { width: 100%; border-collapse: collapse; margin-top: 0; }
th, td { border: 1px solid #ddd; padding: 6px 8px; }
th { background-color: #f0f0f0; font-size: 10px; color: #333; text-align: left; }   
tr:nth-child(even) td { background-color: #f9f9f9; }

/* ── TFOOT ── */
tfoot td {
    border-top: none; border-left: 1px solid #ddd;
    border-right: 1px solid #ddd; border-bottom: 1px solid #ddd;
    background-color: #fff; font-size: 11px; padding: 5px 8px;
}
.total-separador td { border: none; border-top: 2px solid #1B1226; padding: 0; }
.total-fila td { color: #444; }
.total-final td { background-color: #f0f0f0; font-size: 12px; border-top: 2px solid #1B1226; padding: 6px 8px; }

/* ── COLORES ── */
.positivo { color: #1a7a3e; }
.alerta   { color: #9a6800; }
