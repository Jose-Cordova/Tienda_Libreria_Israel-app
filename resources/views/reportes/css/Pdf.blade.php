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
.logo {
    width: 70px;
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

/* ── RESUMEN ── */
.resumen {
    margin-top: 0;
    background-color: #f7f7f7;
    border: 1px solid #ddd;
    border-top: 2px solid #1B1226;
    padding: 8px 10px;
    font-size: 11px;
}

/* ── GANANCIAS ── */
.ganancia-positiva { color: #1a7a3e; font-weight: bold; }
.ganancia-negativa { color: #b03030; font-weight: bold; }

.indicador-positivo {
    margin-top: 10px;
    padding: 8px 12px;
    background-color: #eafaf1;
    border-left: 4px solid #1a7a3e;
    color: #1a7a3e;
    font-weight: bold;
    font-size: 11px;
}
.indicador-negativo {
    margin-top: 10px;
    padding: 8px 12px;
    background-color: #fdedec;
    border-left: 4px solid #b03030;
    color: #b03030;
    font-weight: bold;
    font-size: 11px;
}

/* ── TABLA RESUMEN EJECUTIVO ── */
.tabla-resumen {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
}
.tabla-resumen tr {
    border-bottom: 1px solid #ddd;
}
.tabla-resumen td {
    border: none;
    padding: 8px 10px;
}
.resumen-label {
    width: 45%;
    font-size: 11px;
}
.resumen-valor {
    width: 20%;
    font-size: 13px;
    font-weight: bold;
    text-align: right;
}
.resumen-nota {
    width: 35%;
    font-size: 10px;
    color: #888;
    padding-left: 12px;
}
.resumen-fila-total {
    background-color: #f0f0f0;
    border-top: 2px solid #1B1226 !important;
}
.positivo { color: #1a7a3e; }
.negativo { color: #b03030; }
.alerta   { color: #9a6800; }
