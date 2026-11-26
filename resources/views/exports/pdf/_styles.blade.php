<style>
    @page {
        margin: 13mm 11mm 15mm 11mm;
    }
    * {
        box-sizing: border-box;
    }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8.5pt;
        color: #0F172A;
        margin: 0;
        line-height: 1.35;
    }
    .header-tbl {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 2px solid #0F1E33;
        margin-bottom: 8px;
        padding-bottom: 6px;
    }
    .header-tbl td {
        vertical-align: top;
        padding: 0 0 6px 0;
    }
    .brand {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.8px;
        color: #1D4ED8;
        text-transform: uppercase;
    }
    .doc-title {
        font-size: 13.5pt;
        font-weight: bold;
        color: #0F1E33;
        margin: 2px 0 3px 0;
    }
    .meta-line {
        font-size: 7.5pt;
        color: #475569;
    }
    .meta-right {
        text-align: right;
        font-size: 7.5pt;
        color: #334155;
        white-space: nowrap;
    }
    .filter-box {
        background: #F8FAFC;
        border: 1px solid #CBD5E1;
        border-left: 3px solid #1D4ED8;
        padding: 5px 8px;
        margin-bottom: 9px;
        font-size: 7.8pt;
        color: #334155;
    }
    .kpi-tbl {
        width: 100%;
        border-collapse: separate;
        border-spacing: 6px 0;
        margin: 0 -6px 10px -6px;
    }
    .kpi-card {
        background: #F8FAFC;
        border: 1px solid #CBD5E1;
        padding: 6px 8px;
        text-align: center;
    }
    .kpi-lbl {
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        color: #64748B;
        letter-spacing: 0.4px;
    }
    .kpi-val {
        font-size: 12pt;
        font-weight: bold;
        color: #0F1E33;
        margin-top: 2px;
    }
    .sec-title {
        font-size: 9pt;
        font-weight: bold;
        color: #0F1E33;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin: 10px 0 5px 0;
    }
    table.data-tbl {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    table.data-tbl thead {
        display: table-header-group;
    }
    table.data-tbl tr {
        page-break-inside: avoid;
    }
    table.data-tbl th {
        background: #0F1E33;
        color: #FFFFFF;
        font-weight: bold;
        font-size: 7.5pt;
        padding: 5px 5px;
        border: 1px solid #0F1E33;
        text-align: left;
    }
    table.data-tbl td {
        padding: 4px 5px;
        border: 1px solid #CBD5E1;
        font-size: 7.8pt;
        vertical-align: top;
        word-wrap: break-word;
    }
    table.data-tbl tbody tr:nth-child(even) {
        background: #F8FAFC;
    }
    table.data-tbl tr.total-row td {
        background: #E2E8F0;
        font-weight: bold;
        color: #0F1E33;
        border-top: 1.5px solid #0F1E33;
    }
    .text-center { text-align: center !important; }
    .text-right { text-align: right !important; }
    .num { font-variant-numeric: tabular-nums; }
    .muted { color: #64748B; }
    .bold { font-weight: bold; }
    .badge {
        display: inline-block;
        padding: 1.5px 5px;
        font-size: 6.8pt;
        font-weight: bold;
        border-radius: 3px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .b-empty { background: #1E293B; color: #FFFFFF; }
    .b-crit  { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
    .b-warn  { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
    .b-good  { background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; }
    .b-info  { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
    .b-neu   { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }

    .sign-tbl {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
        page-break-inside: avoid;
    }
    .sign-tbl td {
        width: 33.33%;
        text-align: center;
        vertical-align: top;
        padding: 4px 12px;
        font-size: 8pt;
    }
    .sign-space {
        height: 46px;
    }
    .sign-line {
        border-top: 1px solid #334155;
        width: 75%;
        margin: 0 auto 3px auto;
    }
    .pdf-footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        font-size: 7pt;
        color: #64748B;
        border-top: 1px solid #CBD5E1;
        padding-top: 3px;
    }
    .pdf-footer .page-num:after {
        content: "Halaman " counter(page);
    }
</style>