<style>
    /* ✅ Forzar que el padding no expanda los elementos */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    @page {
        margin: 100px 1.5cm 60px 1.5cm;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #2d2d2d;
        margin: 0;
    }

    /* ── PIE DE PÁGINA FIJO ── */
    .footer-pdf {
        position: fixed;
        bottom: -45px;
        left: 0;
        right: 0;
        width: 100%;
        font-size: 9px;
        color: #666;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* ── ENCABEZADO ── */
    .header-table {
        width: 100%;
        border-bottom: 3px solid #0a3622;
        padding-bottom: 12px;
        margin-bottom: 22px;
        border-collapse: collapse;
    }
    .header-table td {
        border: none;
        padding: 0;
        vertical-align: top;
    }
    .empresa {
        font-size: 19px;
        font-weight: bold;
        color: #0a3622;
    }
    .empresa-detalle {
        font-size: 10px;
        color: #555;
        margin-top: 4px;
    }
    .reporte-info {
        text-align: right;
        color: #333;
    }
    .reporte-titulo {
        font-size: 16px;
        font-weight: bold;
        color: #0a3622;
        letter-spacing: 1px;
    }
    .reporte-periodo {
        font-size: 10px;
        color: #888;
        margin-top: 3px;
    }

    /* ── SECCIÓN ── */
    .seccion-titulo {
        font-size: 12px;
        font-weight: bold;
        margin-top: 22px;
        margin-bottom: 0px;
        padding: 7px 10px;
        background-color: #0a3622;
        color: #ffffff;
        letter-spacing: 0.5px;
        page-break-after: avoid;
    }

    /* ── TABLAS ── */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0px;
        page-break-inside: auto;
    }
    thead {
        display: table-header-group;
    }
    tfoot {
        display: table-footer-group;
    }
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 6px 8px;
    }
    th {
        background-color: #c6e5d3;
        font-size: 11px;
        color: #0a3622;
        text-align: left;
        font-weight: bold;
    }
    td {
        font-size: 11px;
    }
    tr:nth-child(even) td {
        background-color: #f9f9f9;
    }

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
        border-top: 2px solid #0a3622;
        padding: 0;
    }
    .total-final td {
        background-color: #f0f0f0;
        font-size: 12px;
        border-top: 1px solid #bbb;
    }

    /* ── COLORES ── */
    .positivo { color: #0a3622; }
    .negativo { color: #b03030; }
    .alerta   { color: #9a6800; }
    .ganancia-positiva { color: #0a3622; font-weight: bold; }
    .ganancia-negativa { color: #b03030; font-weight: bold; }

    /* ── CUADRO DE TOTALES ── */
    .total-grupo td {
        background-color: #e8e8e8;
        font-size: 11px;
        padding: 6px 8px;
        border: 1px solid #ddd;
        color: #0a3622;
    }

    /* ── RESUMEN FINAL ── */
    .resumen-wrapper {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
        margin-top: 24px;
    }
    .resumen-wrapper > tbody > tr {
        page-break-inside: avoid;
    }
    .resumen-wrapper > tbody > tr > td {
        border: none;
        padding: 0;
    }
    .resumen-contenido {
        border-top: 2px solid #0a3622;
        padding-top: 10px;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
</style>
