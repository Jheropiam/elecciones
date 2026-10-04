@extends('layouts.app')
@section('content')
<style>
.personeros-page .module-card{overflow:hidden}
.personeros-page .regional-module-card{overflow:visible;position:relative;z-index:20}
.personeros-page .module-card-header{padding:22px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;border-bottom:1px solid #edf0f4}
.personeros-page .module-card-header h2{margin:0 0 5px;font-size:24px}
.personeros-page .module-card-header p{margin:0;color:#718096;font-size:14px}
.personeros-page .module-actions{display:flex;gap:9px;flex-wrap:wrap}
.personeros-page .inline-form{padding:22px 28px;background:#fffaf5;border-bottom:1px solid #f1e5d8}
.personeros-page .inline-form.hidden{display:none!important}
.personeros-page .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px 22px}
.personeros-page .field-wide{grid-column:1/-1}
.personeros-page label{display:block;margin-bottom:7px;font-weight:700;color:#334155;font-size:14px}
.personeros-page label span{color:var(--orange-dark)}
.personeros-page input,.personeros-page select{width:100%;box-sizing:border-box;min-height:42px;border:1px solid #d7dde6;border-radius:8px;padding:9px 12px;background:#fff;font-size:14px}
.personeros-page input:focus,.personeros-page select:focus{outline:none;border-color:var(--orange);box-shadow:0 0 0 3px color-mix(in srgb,var(--orange) 12%,transparent)}
.personeros-page .form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
.personeros-page .search-dni{display:flex;gap:8px}
.personeros-page .search-dni input{flex:1}
.personeros-page .person-result{margin-top:6px;color:#64748b;font-size:13px}
.personeros-page .filter-bar{padding:17px 28px;background:#fbfcfe;border-bottom:1px solid #edf0f4}
.personeros-page .filters{display:grid;grid-template-columns:1.4fr repeat(5,minmax(125px,1fr)) auto auto;gap:8px;align-items:center}
.personeros-page .filters input,.personeros-page .filters select{min-height:40px}
.personeros-page .table-wrap{overflow-x:auto}
.personeros-page .data-table{width:100%;border-collapse:collapse}
.personeros-page .data-table th{background:#f7f8fa;color:#526173;font-size:12px;text-transform:uppercase;padding:13px 11px;border-bottom:1px solid #e8ebef;white-space:nowrap}
.personeros-page .data-table td{padding:13px 11px;border-bottom:1px solid #eef1f4;font-size:14px;color:#344255}
.personeros-page .data-table tr:hover{background:#fffaf5}
.personeros-page .strong{font-weight:700;color:#20344c}
.personeros-page .status{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}
.personeros-page .status-active{background:#e9f8ef;color:#18794e}
.personeros-page .status-inactive{background:#f1f3f5;color:#6b7280}
.personeros-page .condition{font-size:12px;font-weight:700;color:#475569}
.personeros-page .icon-btn{width:34px;height:34px;border:1px solid #dce2e9;background:#fff;border-radius:8px;cursor:pointer;margin-right:4px}
.personeros-page .icon-btn:hover{border-color:#ff8a1f;color:#d86a00;background:#fffaf5}
.personeros-page .context-line{display:flex;align-items:center;gap:9px;margin:18px 28px 0;color:#64748b;font-size:14px}.personeros-page .context-line > span:first-child{color:var(--orange);font-weight:700}.personeros-page .context-badge{display:inline-flex;padding:6px 11px;border-radius:999px;background:#f1f3f5;color:#172033;font-weight:800}.personeros-page .progress-card{margin:18px 28px;padding:18px 20px;border:1px solid #e7ebf0;border-radius:12px;background:#fff}
.personeros-page .progress-head{display:flex;justify-content:space-between;gap:15px;align-items:center}
.personeros-page .progress-title{font-weight:800;color:#26364a}
.personeros-page .progress-value{font-weight:800;color:var(--orange-dark)}
.personeros-page .progress-track{height:10px;background:#edf1f5;border-radius:99px;overflow:hidden;margin-top:11px}
.personeros-page .progress-fill{height:100%;background:#ff8a1f;border-radius:99px}
.personeros-page .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:13px}
.personeros-page .summary div{background:#f8fafc;border-radius:9px;padding:10px 12px;color:#64748b;font-size:13px}
.personeros-page .summary b{display:block;color:#26364a;font-size:18px;margin-top:2px}
.personeros-page .regional-personero-header{padding:20px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;border-bottom:1px solid #edf0f4;background:#fff}
.personeros-page .regional-personero-context{display:flex;align-items:center;gap:9px;min-width:0;color:var(--orange);font-size:16px;font-weight:800}
.personeros-page .regional-personero-separator{color:#8a98a9;font-weight:500}
.personeros-page .regional-personero-badge{display:inline-flex;align-items:center;padding:8px 13px;border-radius:999px;background:#f1f3f5;color:#172033;font-weight:800}
.personeros-page .regional-personero-filter{padding:20px 28px;background:#fbfcfe;border-bottom:1px solid #edf0f4;position:relative;z-index:30;overflow:visible}
.personeros-page .regional-personero-filters{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;min-width:0}
.personeros-page .regional-personero-search{flex:0 0 180px;width:180px;min-width:180px;max-width:180px;height:42px;box-sizing:border-box;border:1px solid #d7dde6;border-radius:8px;padding:8px 12px;background:#fff;font-size:16px;color:#24364d;letter-spacing:normal;word-spacing:normal}
.personeros-page .regional-personero-search:focus{outline:none;border-color:var(--orange);box-shadow:0 0 0 3px color-mix(in srgb,var(--orange) 12%,transparent)}
.personeros-page #provinciaPersoneroFilter .regional-personero-search{flex:0 0 267px;width:267px;min-width:267px;max-width:267px;height:42px;font-size:16px;padding:8px 12px}
@media(max-width:760px){.personeros-page #provinciaPersoneroFilter .regional-personero-search{flex:0 1 267px;width:267px;min-width:0;max-width:calc(100vw - 40px)}}
.personeros-page .regional-filter-field{position:relative;flex:0 0 auto;min-width:0}
.personeros-page .regional-filter-field[data-filter="estado"]{flex:0 0 auto;max-width:none}
.personeros-page .regional-filter-trigger{width:auto;min-height:42px;padding:6px 4px 6px 2px;border:0;background:transparent;display:flex;align-items:center;justify-content:flex-start;gap:8px;color:#12375f;font-size:16px;font-weight:800;cursor:pointer;text-align:left}
.personeros-page .regional-filter-trigger:disabled{cursor:default;opacity:1}
.personeros-page .regional-filter-value{min-width:0;white-space:normal;overflow-wrap:normal;word-break:normal;line-height:1.18}
.personeros-page .regional-filter-chevron{width:9px;height:9px;border-right:2px solid var(--orange);border-bottom:2px solid var(--orange);transform:rotate(45deg);flex:0 0 9px;margin-left:1px;margin-top:-4px}
.personeros-page .regional-filter-menu{position:fixed;top:0;left:0;width:320px;max-height:min(365px,70vh);background:#fff;border:1px solid #d8e0e9;border-radius:12px;box-shadow:0 16px 35px rgba(22,34,51,.16);z-index:500;display:flex;flex-direction:column;overflow:hidden}
.personeros-page .regional-filter-menu[hidden]{display:none!important}
.personeros-page .regional-filter-menu-small{width:170px;max-height:none}
.personeros-page .regional-filter-search-wrap{position:relative;flex:0 0 auto;padding:10px 12px;border-bottom:1px solid #edf0f4;background:#fff}
.personeros-page .regional-filter-search{width:100%;height:40px;box-sizing:border-box;border:1px solid #d4dce7;border-radius:8px;padding:8px 10px 8px 32px;font-size:15px;color:#24364d}
.personeros-page .regional-filter-search:focus{outline:none;border-color:var(--orange);box-shadow:0 0 0 3px color-mix(in srgb,var(--orange) 12%,transparent)}
.personeros-page .regional-filter-search-icon{position:absolute;left:20px;top:20px;color:#7890a9;font-size:18px;line-height:1;pointer-events:none}
.personeros-page .regional-filter-options{overflow-y:auto;overflow-x:hidden;min-height:0;max-height:305px;padding:6px;scroll-behavior:auto}
.personeros-page .regional-filter-option{display:block;width:100%;border:0;background:#fff;text-align:left;padding:11px 12px;border-radius:8px;color:#173b65;font-size:15px;line-height:1.25;cursor:pointer;white-space:normal;overflow-wrap:normal;word-break:normal}
.personeros-page .regional-filter-option:hover{background:#f7f9fb}
.personeros-page .regional-filter-option[hidden]{display:none!important}
.personeros-page .regional-filter-option.is-selected{background:var(--orange-soft);color:var(--orange-dark);font-weight:800;box-shadow:inset 3px 0 0 var(--orange)}
.personeros-page .regional-personero-clear{flex:0 0 auto;height:42px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:0 18px;border:1px solid var(--orange);border-radius:8px;background:#fff;color:var(--orange-dark);font-weight:800;font-size:15px;text-decoration:none;white-space:nowrap}
.personeros-page .regional-personero-clear:hover{background:var(--orange-soft)}
@media(max-width:900px){.personeros-page .regional-personero-filters{flex-wrap:wrap}.personeros-page .regional-personero-search{flex:0 1 180px;width:180px;min-width:180px;max-width:180px}.personeros-page .regional-filter-field{flex:0 0 auto}}
@media(max-width:760px){.personeros-page .regional-personero-header{align-items:flex-start;flex-direction:column}.personeros-page .regional-personero-filters{display:grid;grid-template-columns:1fr 1fr;gap:10px}.personeros-page .regional-personero-search{grid-column:1/-1;width:100%;min-width:0;max-width:100%;flex:1 1 auto}.personeros-page .regional-filter-field{width:100%;max-width:none}.personeros-page .regional-personero-clear{width:100%}}
@media(max-width:1100px){.personeros-page .filters{grid-template-columns:repeat(3,1fr)}}
@media(max-width:760px){.personeros-page .form-grid{grid-template-columns:1fr}.personeros-page .filters{grid-template-columns:1fr}.personeros-page .module-card-header{align-items:flex-start;flex-direction:column}.personeros-page .summary{grid-template-columns:1fr}}
.advance-dashboard{margin:24px 28px 30px;padding:28px;border:1px solid #e4e9ef;border-radius:14px;background:linear-gradient(135deg,#fff,#fffaf5);box-shadow:0 5px 20px rgba(22,34,51,.05)}.advance-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}.advance-intro h3{margin:0 0 6px;font-size:24px;color:#24364d}.advance-intro p{margin:0;color:#718096;font-size:14px}.advance-intro>strong{font-size:30px;color:var(--orange-dark)}.advance-track{height:12px;background:#edf1f5;border-radius:99px;overflow:hidden;margin:24px 0}.advance-track div{height:100%;background:linear-gradient(90deg,#f28c28,#ffad54);border-radius:99px}.advance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.advance-grid div{background:#f8fafc;border:1px solid #edf0f4;border-radius:11px;padding:16px}.advance-grid span{display:block;color:#64748b;font-size:13px}.advance-grid b{display:block;margin-top:5px;color:#23364d;font-size:24px}@media(max-width:760px){.advance-grid{grid-template-columns:1fr}.advance-intro{flex-direction:column}}
.personeros-page .regional-personero-header .regional-personero-context{flex-wrap:wrap}
.personeros-page .regional-personero-filter{padding:20px 28px}
.personeros-page .regional-personero-filters .regional-filter-field[data-filter="region"]{min-width:105px}
.personeros-page .regional-personero-filters .regional-filter-field[data-filter="provincia"]{min-width:120px}
.personeros-page .regional-personero-filters .regional-filter-field[data-filter="estado"]{min-width:90px}
@media(max-width:760px){.personeros-page .regional-personero-filters{display:flex;flex-wrap:wrap}.personeros-page .regional-personero-search{flex:1 1 100%;width:100%;max-width:none;min-width:0}.personeros-page .regional-filter-field{flex:1 1 auto}}
/* Ajuste exclusivo de Personero de Provincia: el buscador debe medir como la referencia (~267 px).
   Se aplica al final y con prioridad para que las reglas responsive genéricas no lo expandan. */
.personeros-page #provinciaPersoneroFilter input.regional-personero-search{
  box-sizing:border-box!important; flex:0 0 267px!important; width:267px!important; min-width:267px!important; max-width:267px!important;
}
@media(max-width:760px){
  .personeros-page #provinciaPersoneroFilter input.regional-personero-search,.personeros-page #regionalPersoneroFilter input.regional-personero-search{
    flex:0 1 267px!important; width:267px!important; min-width:0!important; max-width:calc(100vw - 40px)!important;
  }
}

.personeros-page #distritoPersoneroFilter{padding:17px 28px;background:#fbfcfe;border-bottom:1px solid #edf0f4;position:relative;z-index:30;overflow:visible}
.personeros-page #distritoPersoneroFilter .regional-personero-search{flex:0 0 267px;width:267px;min-width:267px;max-width:267px;height:42px}
.personeros-page #distritoPersoneroFilter .regional-personero-filters{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;min-width:0}
@media(max-width:900px){.personeros-page #distritoPersoneroFilter .regional-personero-filters{flex-wrap:wrap}}
@media(max-width:760px){.personeros-page #distritoPersoneroFilter .regional-personero-filters{display:flex;flex-wrap:wrap}.personeros-page #distritoPersoneroFilter .regional-personero-search{flex:0 1 267px;width:267px;min-width:0;max-width:calc(100vw - 40px)}}

/* Ajustes exclusivos de Personero de Local y Personero de Mesa:
   evitar que la barra de filtros se salga del contenedor y permitir que
   nombres territoriales largos se distribuyan en varias líneas. */
.personeros-page.personeros-type-local .regional-personero-filter,
.personeros-page.personeros-type-mesa .regional-personero-filter{
  padding:14px 22px;
  overflow:visible;
}
.personeros-page.personeros-type-local .regional-personero-filters,
.personeros-page.personeros-type-mesa .regional-personero-filters{
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:6px 12px;
  min-width:0;
  width:100%;
}
.personeros-page.personeros-type-local .regional-personero-search,
.personeros-page.personeros-type-mesa .regional-personero-search{
  flex:0 0 200px;
  width:200px;
  min-width:160px;
  max-width:200px;
  height:38px;
  padding:7px 10px;
  font-size:13px;
}
.personeros-page.personeros-type-local .regional-filter-field,
.personeros-page.personeros-type-mesa .regional-filter-field{
  flex:0 1 auto;
  min-width:0;
  max-width:100%;
}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="region"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="region"]{max-width:130px}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="provincia"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="provincia"]{max-width:145px}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="distrito"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="distrito"]{max-width:155px}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="local"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="local"]{flex:0 1 250px;max-width:270px}
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="mesa"]{max-width:120px}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="estado"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="estado"]{max-width:100px}
.personeros-page.personeros-type-local .regional-filter-trigger,
.personeros-page.personeros-type-mesa .regional-filter-trigger{
  max-width:100%;
  min-height:38px;
  padding:5px 3px;
  gap:6px;
  font-size:13px;
  line-height:1.2;
}
.personeros-page.personeros-type-local .regional-filter-value,
.personeros-page.personeros-type-mesa .regional-filter-value{
  display:block;
  min-width:0;
  max-width:100%;
  white-space:normal;
  overflow-wrap:anywhere;
  word-break:normal;
  line-height:1.2;
}
.personeros-page.personeros-type-local .regional-personero-clear,
.personeros-page.personeros-type-mesa .regional-personero-clear{
  flex:0 0 auto;
  height:38px;
  margin-left:auto;
  padding:0 13px;
  font-size:13px;
}
.personeros-page.personeros-type-local .data-table,
.personeros-page.personeros-type-mesa .data-table{table-layout:auto;min-width:100%}
.personeros-page.personeros-type-local .data-table th,
.personeros-page.personeros-type-mesa .data-table th{font-size:11px;padding:10px 8px}
.personeros-page.personeros-type-local .data-table td,
.personeros-page.personeros-type-mesa .data-table td{
  font-size:12px;
  padding:10px 8px;
  white-space:normal;
  overflow-wrap:anywhere;
  word-break:normal;
  line-height:1.35;
}
.personeros-page.personeros-type-local .data-table td:nth-child(3),
.personeros-page.personeros-type-mesa .data-table td:nth-child(3){min-width:130px;max-width:210px}
.personeros-page.personeros-type-local .data-table td:nth-child(7),
.personeros-page.personeros-type-mesa .data-table td:nth-child(7),
.personeros-page.personeros-type-local .data-table td:nth-child(8),
.personeros-page.personeros-type-mesa .data-table td:nth-child(8){max-width:190px}
@media(max-width:900px){
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters{gap:8px 10px}
  .personeros-page.personeros-type-local .regional-personero-search,
  .personeros-page.personeros-type-mesa .regional-personero-search{flex-basis:200px}
  .personeros-page.personeros-type-local .regional-filter-field[data-filter="local"],
  .personeros-page.personeros-type-mesa .regional-filter-field[data-filter="local"]{flex-basis:220px}
}
@media(max-width:600px){
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters{display:flex;flex-wrap:wrap;align-items:center}
  .personeros-page.personeros-type-local .regional-personero-search,
  .personeros-page.personeros-type-mesa .regional-personero-search{flex:1 1 100%;width:100%;min-width:0;max-width:100%}
  .personeros-page.personeros-type-local .regional-personero-clear,
  .personeros-page.personeros-type-mesa .regional-personero-clear{margin-left:0}
}

/* Ajuste final solicitado: búsqueda de 150 px y Limpiar inmediatamente después
   del último filtro, sin empujarlo al extremo derecho. Solo Local y Mesa. */
.personeros-page.personeros-type-local .regional-personero-search,
.personeros-page.personeros-type-mesa .regional-personero-search{
  flex:0 0 150px !important;
  width:150px !important;
  min-width:150px !important;
  max-width:150px !important;
  box-sizing:border-box;
}
.personeros-page.personeros-type-local .regional-personero-clear,
.personeros-page.personeros-type-mesa .regional-personero-clear{
  margin-left:0 !important;
  flex:0 0 auto;
  justify-self:start;
}
@media(max-width:600px){
  .personeros-page.personeros-type-local .regional-personero-search,
  .personeros-page.personeros-type-mesa .regional-personero-search{
    flex:0 0 150px !important;
    width:150px !important;
    min-width:150px !important;
    max-width:150px !important;
  }
}

/* Ajuste de compactación: únicamente Personero de Local y Personero de Mesa.
   Mantiene todos los filtros agrupados a la izquierda, como en la referencia. */
.personeros-page.personeros-type-local .regional-personero-filters,
.personeros-page.personeros-type-mesa .regional-personero-filters {
  justify-content: flex-start !important;
  column-gap: 0 !important;
  row-gap: 6px !important;
}
.personeros-page.personeros-type-local .regional-filter-field,
.personeros-page.personeros-type-mesa .regional-filter-field {
  flex-grow: 0 !important;
  margin: 0 !important;
  padding: 0 8px;
  background: #f3f5f8;
  align-self: stretch;
  display: flex;
  align-items: center;
}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="region"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="region"] { min-width: 96px; max-width: 130px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="provincia"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="provincia"] { min-width: 108px; max-width: 145px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="distrito"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="distrito"] { min-width: 112px; max-width: 155px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="local"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="local"] { flex: 0 1 250px !important; max-width: 270px; min-width: 0; }
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="mesa"] { min-width: 82px; max-width: 120px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="estado"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="estado"] { min-width: 78px; max-width: 100px; }
.personeros-page.personeros-type-local .regional-filter-trigger,
.personeros-page.personeros-type-mesa .regional-filter-trigger { width: auto; max-width: 100%; }
.personeros-page.personeros-type-local .regional-personero-clear,
.personeros-page.personeros-type-mesa .regional-personero-clear {
  margin-left: 8px !important;
  align-self: center;
}
@media (max-width: 900px) {
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters { flex-wrap: wrap; }
}


/* Corrección final de presentación: Personero de Local y de Mesa.
   Sin fondos grises ni espacios amplios; los filtros y Limpiar permanecen juntos. */
.personeros-page.personeros-type-local .regional-personero-filter,
.personeros-page.personeros-type-mesa .regional-personero-filter {
  background: #fff !important;
}
.personeros-page.personeros-type-local .regional-personero-filters,
.personeros-page.personeros-type-mesa .regional-personero-filters {
  display: flex !important;
  flex-flow: row nowrap !important;
  justify-content: flex-start !important;
  align-items: center !important;
  gap: 0 !important;
  width: max-content;
  max-width: 100%;
  min-width: 0;
}
.personeros-page.personeros-type-local .regional-filter-field,
.personeros-page.personeros-type-mesa .regional-filter-field {
  background: transparent !important;
  padding: 0 5px !important;
  margin: 0 !important;
  flex-shrink: 1;
  align-self: center;
  min-width: 0;
}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="region"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="region"] { flex: 0 1 105px; width: 105px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="provincia"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="provincia"] { flex: 0 1 110px; width: 110px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="distrito"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="distrito"] { flex: 0 1 115px; width: 115px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="local"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="local"] { flex: 0 1 230px !important; width: 230px; max-width: 230px; }
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="mesa"] { flex: 0 1 95px; width: 95px; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="estado"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="estado"] { flex: 0 1 82px; width: 82px; }
.personeros-page.personeros-type-local .regional-filter-trigger,
.personeros-page.personeros-type-mesa .regional-filter-trigger {
  padding: 4px 2px !important;
  gap: 4px !important;
  font-size: 13px !important;
  min-width: 0;
}
.personeros-page.personeros-type-local .regional-filter-value,
.personeros-page.personeros-type-mesa .regional-filter-value {
  white-space: normal !important;
  overflow-wrap: anywhere;
  word-break: normal;
}
.personeros-page.personeros-type-local .regional-personero-clear,
.personeros-page.personeros-type-mesa .regional-personero-clear {
  margin: 0 0 0 3px !important;
  flex: 0 0 auto !important;
  align-self: center !important;
  white-space: nowrap;
}
@media (max-width: 1050px) {
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters {
    flex-wrap: wrap !important;
    width: 100%;
    row-gap: 4px !important;
  }
}
@media (max-width: 600px) {
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters { flex-wrap: wrap !important; }
  .personeros-page.personeros-type-local .regional-personero-search,
  .personeros-page.personeros-type-mesa .regional-personero-search { flex: 0 0 150px !important; width: 150px !important; min-width: 150px !important; max-width: 150px !important; }
}


/* Corrección final de alineación: únicamente Personero de Local y Personero de Mesa.
   Los filtros y Limpiar permanecen agrupados a la izquierda, también cuando se selecciona distrito/local/mesa. */
.personeros-page.personeros-type-local .regional-personero-filters,
.personeros-page.personeros-type-mesa .regional-personero-filters {
  display: flex !important;
  flex-wrap: nowrap !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 4px !important;
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
}
.personeros-page.personeros-type-local .regional-personero-search,
.personeros-page.personeros-type-mesa .regional-personero-search {
  flex: 0 0 150px !important;
  width: 150px !important;
  min-width: 150px !important;
  max-width: 150px !important;
  margin: 0 !important;
}
.personeros-page.personeros-type-local .regional-filter-field,
.personeros-page.personeros-type-mesa .regional-filter-field {
  flex: 0 1 auto !important;
  width: auto !important;
  max-width: none !important;
  min-width: 0 !important;
  margin: 0 !important;
  padding: 0 3px !important;
  background: transparent !important;
  align-self: center !important;
}
.personeros-page.personeros-type-local .regional-filter-field[data-filter="region"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="region"] { flex-basis: 100px !important; width: 100px !important; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="provincia"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="provincia"] { flex-basis: 108px !important; width: 108px !important; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="distrito"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="distrito"] { flex-basis: 108px !important; width: 108px !important; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="local"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="local"] { flex: 0 1 220px !important; width: 220px !important; max-width: 220px !important; }
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="mesa"] { flex: 0 1 92px !important; width: 92px !important; }
.personeros-page.personeros-type-local .regional-filter-field[data-filter="estado"],
.personeros-page.personeros-type-mesa .regional-filter-field[data-filter="estado"] { flex: 0 1 78px !important; width: 78px !important; }
.personeros-page.personeros-type-local .regional-filter-trigger,
.personeros-page.personeros-type-mesa .regional-filter-trigger {
  display: flex !important;
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
  padding: 4px 2px !important;
  gap: 4px !important;
  font-size: 13px !important;
  line-height: 1.2 !important;
}
.personeros-page.personeros-type-local .regional-filter-value,
.personeros-page.personeros-type-mesa .regional-filter-value {
  display: block !important;
  min-width: 0 !important;
  max-width: 100% !important;
  white-space: normal !important;
  overflow-wrap: anywhere !important;
  word-break: normal !important;
  line-height: 1.2 !important;
}
.personeros-page.personeros-type-local .regional-personero-clear,
.personeros-page.personeros-type-mesa .regional-personero-clear {
  flex: 0 0 auto !important;
  margin: 0 !important;
  align-self: center !important;
  height: 38px !important;
  padding: 0 13px !important;
  font-size: 13px !important;
  white-space: nowrap !important;
}
@media (max-width: 1050px) {
  .personeros-page.personeros-type-local .regional-personero-filters,
  .personeros-page.personeros-type-mesa .regional-personero-filters {
    flex-wrap: wrap !important;
    row-gap: 6px !important;
  }
  .personeros-page.personeros-type-local .regional-personero-clear,
  .personeros-page.personeros-type-mesa .regional-personero-clear {
    margin-left: 0 !important;
  }
}
@media (max-width: 600px) {
  .personeros-page.personeros-type-local .regional-personero-search,
  .personeros-page.personeros-type-mesa .regional-personero-search {
    flex: 0 0 150px !important;
    width: 150px !important;
    min-width: 150px !important;
    max-width: 150px !important;
  }
}

</style>

<div class="page personeros-page personeros-type-{{ $tipo }}">
  @if(!in_array($tipo, ['regional','provincia','distrito','local','mesa']))
  <div class="page-heading">
    <div><div class="breadcrumb">Inicio <span>›</span> Personeros</div><h1>Personeros</h1><p class="page-subtitle">Registro, asignación y seguimiento de personeros.</p></div>
  </div>
  @endif

  <div class="module-card {{ in_array($tipo, ['regional','provincia','distrito','local','mesa']) ? 'regional-module-card' : '' }}">
    @if($tipo === 'provincia')
      <div class="regional-personero-header">
        <div class="regional-personero-context"><span>Personeros</span><span class="regional-personero-separator">›</span><span class="regional-personero-badge">Personero de Provincia</span></div>
        <div class="module-actions">
          <a class="btn btn-light" href="{{ route('personeros.template',$tipo) }}">Plantilla Excel</a>
          <a class="btn btn-light" href="{{ route('personeros.export',$tipo) }}">Exportar Excel</a>
          <button type="button" class="btn btn-light" id="btnImportPersonero">Importar Excel</button>
          <button type="button" class="btn btn-primary" id="btnNuevoPersonero">+ Nuevo</button>
        </div>
      </div>
    @elseif($tipo === 'regional')
      <div class="regional-personero-header">
        <div class="regional-personero-context">
          <span>Personeros</span><span class="regional-personero-separator">›</span>
          <span class="regional-personero-badge">Personero Regional</span>
        </div>
        @if(!$esAvance)
        <div class="module-actions">
          <a class="btn btn-light" href="{{ route('personeros.template',$tipo) }}">Plantilla Excel</a>
          <a class="btn btn-light" href="{{ route('personeros.export',$tipo) }}">Exportar Excel</a>
          <button type="button" class="btn btn-light" id="btnImportPersonero">Importar Excel</button>
          <button type="button" class="btn btn-primary" id="btnNuevoPersonero">+ Nuevo</button>
        </div>
        @endif
      </div>
    @elseif($tipo === 'local')
      <div class="regional-personero-header">
        <div class="regional-personero-context"><span>Personeros</span><span class="regional-personero-separator">›</span><span class="regional-personero-badge">Personero de Local</span></div>
        @if(!$esAvance)
        <div class="module-actions">
          <a class="btn btn-light" href="{{ route('personeros.template',$tipo) }}">Plantilla Excel</a>
          <a class="btn btn-light" href="{{ route('personeros.export',$tipo) }}">Exportar Excel</a>
          <button type="button" class="btn btn-light" id="btnImportPersonero">Importar Excel</button>
          <button type="button" class="btn btn-primary" id="btnNuevoPersonero">+ Nuevo</button>
        </div>
        @endif
      </div>
    @elseif($tipo === 'mesa')
      <div class="regional-personero-header">
        <div class="regional-personero-context"><span>Personeros</span><span class="regional-personero-separator">›</span><span class="regional-personero-badge">Personero de Mesa</span></div>
        @if(!$esAvance)
        <div class="module-actions">
          <a class="btn btn-light" href="{{ route('personeros.template',$tipo) }}">Plantilla Excel</a>
          <a class="btn btn-light" href="{{ route('personeros.export',$tipo) }}">Exportar Excel</a>
          <button type="button" class="btn btn-light" id="btnImportPersonero">Importar Excel</button>
          <button type="button" class="btn btn-primary" id="btnNuevoPersonero">+ Nuevo</button>
        </div>
        @endif
      </div>
    @elseif($tipo === 'distrito')
      <div class="regional-personero-header">
        <div class="regional-personero-context"><span>Personeros</span><span class="regional-personero-separator">›</span><span class="regional-personero-badge">Personero de Distrito</span></div>
        @if(!$esAvance)
        <div class="module-actions">
          <a class="btn btn-light" href="{{ route('personeros.template',$tipo) }}">Plantilla Excel</a>
          <a class="btn btn-light" href="{{ route('personeros.export',$tipo) }}">Exportar Excel</a>
          <button type="button" class="btn btn-light" id="btnImportPersonero">Importar Excel</button>
          <button type="button" class="btn btn-primary" id="btnNuevoPersonero">+ Nuevo</button>
        </div>
        @endif
      </div>

      @if(!$esAvance && $tipo !== 'distrito')
        <div class="context-line"><span>Personeros</span><span>›</span><span class="context-badge">{{ ['provincia'=>'Personero de Provincia','distrito'=>'Personero de Distrito','local'=>'Personero de Local','mesa'=>'Personero de Mesa'][$tipo] }}</span></div>
      @endif
    @endif

    @if($esAvance)
      <div class="advance-dashboard">
        <div class="advance-intro"><div><h3>Avance de personeros de mesa</h3><p>Seguimiento de las mesas registradas con personero de mesa titular activo.</p></div><strong>{{ number_format($avance['porcentaje'],2) }}%</strong></div>
        <div class="advance-track"><div style="width:{{ min(100,$avance['porcentaje']) }}%"></div></div>
        <div class="advance-grid"><div><span>Mesas registradas</span><b>{{ $avance['total_mesas'] }}</b></div><div><span>Con personero titular</span><b>{{ $avance['cubiertas'] }}</b></div><div><span>Pendientes</span><b>{{ $avance['pendientes'] }}</b></div></div>
      </div>
    @else
      <div id="import-personero" class="inline-form hidden">
      <form method="POST" action="{{ route('personeros.import',$tipo) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
          <div class="field field-wide"><label>Archivo Excel (.xlsx) <span>*</span></label><input name="archivo" type="file" accept=".xlsx" required><small>Use la plantilla oficial. Máximo 5 MB.</small></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary">Procesar importación</button><button type="button" class="btn btn-light" onclick="toggleForm('import-personero')">Cancelar</button></div>
      </form>
    </div>

    <div id="form-personero" class="inline-form hidden">
      <form id="personeroForm" method="POST" action="{{ route('personeros.store',$tipo) }}">
        @csrf
        <input type="hidden" name="_method" id="personeroMethod" value="">
        <input type="hidden" name="_form" value="personero">
        <div class="form-grid">
          <div class="field field-wide">
            <label>DNI de la persona <span>*</span></label>
            <div class="search-dni"><input id="dniPersonero" maxlength="20" placeholder="Ingrese DNI"><button type="button" class="btn btn-light" id="buscarPersonero">Buscar</button></div>
            <div class="person-result" id="personaMsg">Primero busque la persona registrada en Personas.</div>
            <input type="hidden" name="id_persona" id="idPersona">
          </div>
          <div class="field field-wide"><label>Persona</label><input id="nombrePersona" readonly placeholder="Se mostrará después de buscar el DNI"></div>

          <div class="field {{ $tipo==='regional'?'field-wide':'' }}" data-scope="regional">
            <label>Región <span>*</span></label>
            <select name="id_region" id="pRegion"><option value="">Seleccione</option>@foreach($regiones as $r)<option value="{{ $r->id_region }}">{{ $r->nombre }}</option>@endforeach</select>
          </div>
          @if($tipo==='provincia' || $tipo==='distrito' || $tipo==='local' || $tipo==='mesa')
          <div class="field" data-scope="provincia"><label>Provincia <span>*</span></label><select name="id_provincia" id="pProvincia"><option value="">Seleccione</option></select></div>
          @endif
          @if($tipo==='distrito' || $tipo==='local' || $tipo==='mesa')
          <div class="field" data-scope="distrito"><label>Distrito <span>*</span></label><select name="id_distrito" id="pDistrito"><option value="">Seleccione</option></select></div>
          @endif
          @if($tipo==='local' || $tipo==='mesa')
          <div class="field" data-scope="local"><label>Local <span>*</span></label><select name="id_local" id="pLocal"><option value="">Seleccione</option></select></div>
          @endif
          @if($tipo==='mesa')
          <div class="field" data-scope="mesa"><label>Mesa <span>*</span></label><select name="id_mesa" id="pMesa"><option value="">Seleccione</option></select></div>
          @endif

          <div class="field"><label>Condición <span>*</span></label>
            <select name="condicion" required>
              <option value="">Seleccione</option>
              <option value="TITULAR">Titular</option>
              <option value="PRIMER_SUPLENTE">Primer suplente</option>
              <option value="SEGUNDO_SUPLENTE">Segundo suplente</option>
              <option value="TERCER_SUPLENTE">Tercer suplente</option>
            </select>
          </div>
        </div>
        <div class="form-actions"><button type="button" class="btn btn-light" id="cancelPersonero">Cancelar</button><button class="btn btn-primary" id="savePersonero">Registrar personero</button></div>
      </form>
    </div>

    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="validation-summary"><strong>Revise los datos:</strong><ul style="margin:6px 0 0 18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @if(session('import_summary'))<div class="import-summary"><b>Resultado de importación</b><div class="import-summary-grid"><span>Procesados <b>{{ session('import_summary.procesados') }}</b></span><span>Nuevos <b>{{ session('import_summary.nuevos') }}</b></span><span>Duplicados <b>{{ session('import_summary.duplicados') }}</b></span><span>Errores <b>{{ session('import_summary.errores') }}</b></span></div>@if(session('import_error_file'))<a href="{{ route('personeros.import.errors',session('import_error_file')) }}">Descargar Excel de errores</a>@endif</div>@endif

    @if($tipo === 'provincia')
      <form class="regional-personero-filter" id="provinciaPersoneroFilter" method="GET">
        <input type="hidden" name="tipo" value="provincia">
        <div class="regional-personero-filters">
          <input class="regional-personero-search" name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos" aria-label="Buscar por DNI, nombres o apellidos">
          <div class="regional-filter-field" data-filter="region">
            <button type="button" class="regional-filter-trigger" aria-expanded="false" @disabled($bloquearFiltroRegion ?? false)><span class="regional-filter-value">{{ optional($regiones->firstWhere('id_region', $region))->nombre ?? 'Región' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar región..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($regiones as $r)<button type="button" class="regional-filter-option {{ (string)$region === (string)$r->id_region && $region !== null && $region !== '' ? 'is-selected' : '' }}" data-value="{{ $r->id_region }}">{{ $r->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="region" value="{{ $region ?? '' }}">
          </div>
          @if($region !== null && $region !== '')
          <div class="regional-filter-field" data-filter="provincia">
            <button type="button" class="regional-filter-trigger" aria-expanded="false" @disabled($bloquearFiltroProvincia ?? false)><span class="regional-filter-value">{{ optional($provincias->firstWhere('id_provincia', $provincia))->nombre ?? 'Provincia' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar provincia..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($provincias as $p)<button type="button" class="regional-filter-option {{ (string)$provincia === (string)$p->id_provincia && $provincia !== null && $provincia !== '' ? 'is-selected' : '' }}" data-value="{{ $p->id_provincia }}">{{ $p->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="provincia" value="{{ $provincia ?? '' }}">
          </div>
          @endif
          @if($provincia !== null && $provincia !== '')
          <div class="regional-filter-field" data-filter="estado">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ $estado === '1' ? 'Activos' : ($estado === '0' ? 'Inactivos' : 'Estado') }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu regional-filter-menu-small" hidden><div class="regional-filter-options"><button type="button" class="regional-filter-option {{ $estado === '' ? 'is-selected' : '' }}" data-value="">Todos</button><button type="button" class="regional-filter-option {{ $estado === '1' ? 'is-selected' : '' }}" data-value="1">Activos</button><button type="button" class="regional-filter-option {{ $estado === '0' ? 'is-selected' : '' }}" data-value="0">Inactivos</button></div></div><input type="hidden" name="estado" value="{{ $estado }}">
          </div>
          @endif
          <a class="regional-personero-clear" href="{{ route('personeros.index',['tipo'=>'provincia']) }}" title="Limpiar filtros"><span aria-hidden="true">↻</span> Limpiar</a>
        </div>
      </form>
    @elseif($tipo === 'regional')
      <form class="regional-personero-filter" method="GET" id="regionalPersoneroFilter">
        <input type="hidden" name="tipo" value="regional">
        <div class="regional-personero-filters">
          <input class="regional-personero-search" name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos" aria-label="Buscar por DNI, nombres o apellidos">

          <div class="regional-filter-field" data-filter="region">
            <button type="button" class="regional-filter-trigger" aria-expanded="false">
              <span class="regional-filter-value">{{ optional($regiones->firstWhere('id_region', $region))->nombre ?? 'Región' }}</span>
              <span class="regional-filter-chevron" aria-hidden="true"></span>
            </button>
            <div class="regional-filter-menu" hidden>
              <div class="regional-filter-search-wrap">
                <span class="regional-filter-search-icon" aria-hidden="true">⌕</span>
                <input type="search" class="regional-filter-search" placeholder="Buscar región..." autocomplete="off">
              </div>
              <div class="regional-filter-options">
                @foreach($regiones as $r)
                  <button type="button" class="regional-filter-option {{ (string)$region === (string)$r->id_region && $region !== null && $region !== '' ? 'is-selected' : '' }}" data-value="{{ $r->id_region }}">{{ $r->nombre }}</button>
                @endforeach
              </div>
            </div>
            <input type="hidden" name="region" value="{{ $region ?? '' }}">
          </div>

          @if($region !== null && $region !== '')
          <div class="regional-filter-field" data-filter="estado">
            <button type="button" class="regional-filter-trigger" aria-expanded="false">
              <span class="regional-filter-value">{{ $estado === '1' ? 'Activos' : ($estado === '0' ? 'Inactivos' : 'Estado') }}</span>
              <span class="regional-filter-chevron" aria-hidden="true"></span>
            </button>
            <div class="regional-filter-menu regional-filter-menu-small" hidden>
              <div class="regional-filter-options">
                <button type="button" class="regional-filter-option {{ $estado === '' ? 'is-selected' : '' }}" data-value="">Todos</button>
                <button type="button" class="regional-filter-option {{ $estado === '1' ? 'is-selected' : '' }}" data-value="1">Activos</button>
                <button type="button" class="regional-filter-option {{ $estado === '0' ? 'is-selected' : '' }}" data-value="0">Inactivos</button>
              </div>
            </div>
            <input type="hidden" name="estado" value="{{ $estado }}">
          </div>
          @endif

          <a class="regional-personero-clear" href="{{ route('personeros.index',['tipo'=>'regional']) }}" title="Limpiar filtros">
            <span aria-hidden="true">↻</span> Limpiar
          </a>
        </div>
      </form>
    @elseif($tipo === 'distrito')
      <form class="regional-personero-filter" id="distritoPersoneroFilter" method="GET">
        <input type="hidden" name="tipo" value="distrito">
        <div class="regional-personero-filters">
          <input class="regional-personero-search" name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos" aria-label="Buscar por DNI, nombres o apellidos">
          <div class="regional-filter-field" data-filter="region">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($regiones->firstWhere('id_region', $region))->nombre ?? 'Región' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar región..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($regiones as $r)<button type="button" class="regional-filter-option {{ (string)$region === (string)$r->id_region && $region !== null && $region !== '' ? 'is-selected' : '' }}" data-value="{{ $r->id_region }}">{{ $r->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="region" value="{{ $region ?? '' }}">
          </div>
          @if($region !== null && $region !== '')
          <div class="regional-filter-field" data-filter="provincia">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($provincias->firstWhere('id_provincia', $provincia))->nombre ?? 'Provincia' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar provincia..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($provincias as $p)<button type="button" class="regional-filter-option {{ (string)$provincia === (string)$p->id_provincia && $provincia !== null && $provincia !== '' ? 'is-selected' : '' }}" data-value="{{ $p->id_provincia }}">{{ $p->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="provincia" value="{{ $provincia ?? '' }}">
          </div>
          @endif
          @if($provincia !== null && $provincia !== '')
          <div class="regional-filter-field" data-filter="distrito">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($distritos->firstWhere('id_distrito', $distrito))->nombre ?? 'Distrito' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar distrito..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($distritos as $d)<button type="button" class="regional-filter-option {{ (string)$distrito === (string)$d->id_distrito && $distrito !== null && $distrito !== '' ? 'is-selected' : '' }}" data-value="{{ $d->id_distrito }}">{{ $d->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="distrito" value="{{ $distrito ?? '' }}">
          </div>
          @endif
          @if($distrito !== null && $distrito !== '')
          <div class="regional-filter-field" data-filter="estado"><button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ $estado === '1' ? 'Activos' : ($estado === '0' ? 'Inactivos' : 'Estado') }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button><div class="regional-filter-menu regional-filter-menu-small" hidden><div class="regional-filter-options"><button type="button" class="regional-filter-option {{ $estado === '' ? 'is-selected' : '' }}" data-value="">Todos</button><button type="button" class="regional-filter-option {{ $estado === '1' ? 'is-selected' : '' }}" data-value="1">Activos</button><button type="button" class="regional-filter-option {{ $estado === '0' ? 'is-selected' : '' }}" data-value="0">Inactivos</button></div></div><input type="hidden" name="estado" value="{{ $estado }}"></div>
          @endif
          <a class="regional-personero-clear" href="{{ route('personeros.index',['tipo'=>'distrito']) }}" title="Limpiar filtros"><span aria-hidden="true">↻</span> Limpiar</a>
        </div>
      </form>
    @elseif(in_array($tipo, ['local','mesa']))
      <form class="regional-personero-filter" id="{{ $tipo }}PersoneroFilter" method="GET">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="regional-personero-filters">
          <input class="regional-personero-search" name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos" aria-label="Buscar por DNI, nombres o apellidos">
          <div class="regional-filter-field" data-filter="region">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($regiones->firstWhere('id_region', $region))->nombre ?? 'Región' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar región..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($regiones as $r)<button type="button" class="regional-filter-option {{ (string)$region === (string)$r->id_region && $region !== null && $region !== '' ? 'is-selected' : '' }}" data-value="{{ $r->id_region }}">{{ $r->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="region" value="{{ $region ?? '' }}">
          </div>
          @if($region !== null && $region !== '')
          <div class="regional-filter-field" data-filter="provincia">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($provincias->firstWhere('id_provincia', $provincia))->nombre ?? 'Provincia' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar provincia..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($provincias as $p)<button type="button" class="regional-filter-option {{ (string)$provincia === (string)$p->id_provincia && $provincia !== null && $provincia !== '' ? 'is-selected' : '' }}" data-value="{{ $p->id_provincia }}">{{ $p->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="provincia" value="{{ $provincia ?? '' }}">
          </div>
          @endif
          @if($provincia !== null && $provincia !== '')
          <div class="regional-filter-field" data-filter="distrito">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($distritos->firstWhere('id_distrito', $distrito))->nombre ?? 'Distrito' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar distrito..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($distritos as $d)<button type="button" class="regional-filter-option {{ (string)$distrito === (string)$d->id_distrito && $distrito !== null && $distrito !== '' ? 'is-selected' : '' }}" data-value="{{ $d->id_distrito }}">{{ $d->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="distrito" value="{{ $distrito ?? '' }}">
          </div>
          @endif
          @if(($tipo === 'local' || $tipo === 'mesa') && $distrito !== null && $distrito !== '')
          <div class="regional-filter-field" data-filter="local">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($locales->firstWhere('id_local', $local))->nombre ?? 'Local' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar local..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($locales as $l)<button type="button" class="regional-filter-option {{ (string)$local === (string)$l->id_local && $local !== null && $local !== '' ? 'is-selected' : '' }}" data-value="{{ $l->id_local }}">{{ $l->nombre }}</button>@endforeach
            </div></div><input type="hidden" name="local" value="{{ $local ?? '' }}">
          </div>
          @endif
          @if($tipo === 'mesa' && $local !== null && $local !== '')
          <div class="regional-filter-field" data-filter="mesa">
            <button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ optional($mesas->firstWhere('id_mesa', $mesa))->numero_mesa ? 'Mesa '.optional($mesas->firstWhere('id_mesa', $mesa))->numero_mesa : 'Mesa' }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button>
            <div class="regional-filter-menu" hidden><div class="regional-filter-search-wrap"><span class="regional-filter-search-icon" aria-hidden="true">⌕</span><input type="search" class="regional-filter-search" placeholder="Buscar mesa..." autocomplete="off"></div><div class="regional-filter-options">
              @foreach($mesas as $m)<button type="button" class="regional-filter-option {{ (string)$mesa === (string)$m->id_mesa && $mesa !== null && $mesa !== '' ? 'is-selected' : '' }}" data-value="{{ $m->id_mesa }}">Mesa {{ $m->numero_mesa }}</button>@endforeach
            </div></div><input type="hidden" name="mesa" value="{{ $mesa ?? '' }}">
          </div>
          @endif
          @if(($tipo === 'local' && $local !== null && $local !== '') || ($tipo === 'mesa' && $mesa !== null && $mesa !== ''))
          <div class="regional-filter-field" data-filter="estado"><button type="button" class="regional-filter-trigger" aria-expanded="false"><span class="regional-filter-value">{{ $estado === '1' ? 'Activos' : ($estado === '0' ? 'Inactivos' : 'Estado') }}</span><span class="regional-filter-chevron" aria-hidden="true"></span></button><div class="regional-filter-menu regional-filter-menu-small" hidden><div class="regional-filter-options"><button type="button" class="regional-filter-option {{ $estado === '' ? 'is-selected' : '' }}" data-value="">Todos</button><button type="button" class="regional-filter-option {{ $estado === '1' ? 'is-selected' : '' }}" data-value="1">Activos</button><button type="button" class="regional-filter-option {{ $estado === '0' ? 'is-selected' : '' }}" data-value="0">Inactivos</button></div></div><input type="hidden" name="estado" value="{{ $estado }}"></div>
          @endif
          <a class="regional-personero-clear" href="{{ route('personeros.index',['tipo'=>$tipo]) }}" title="Limpiar filtros"><span aria-hidden="true">↻</span> Limpiar</a>
        </div>
      </form>
    @else
      <form class="filter-bar" method="GET">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="filters">
          <input name="q" value="{{ $q }}" placeholder="DNI, nombres o apellidos">
          <select name="region" id="fRegion"><option value="">Todas las regiones</option>@foreach($regiones as $r)<option value="{{ $r->id_region }}" @selected((string)$region===(string)$r->id_region)>{{ $r->nombre }}</option>@endforeach</select>
          @if($tipo!=='regional')<select name="provincia" id="fProvincia"><option value="">Todas las provincias</option>@foreach($provincias as $p)<option value="{{ $p->id_provincia }}" @selected((string)$provincia===(string)$p->id_provincia)>{{ $p->nombre }}</option>@endforeach</select>@endif
          @if(in_array($tipo,['distrito','local','mesa']))<select name="distrito" id="fDistrito"><option value="">Todos los distritos</option>@foreach($distritos as $d)<option value="{{ $d->id_distrito }}" @selected((string)$distrito===(string)$d->id_distrito)>{{ $d->nombre }}</option>@endforeach</select>@endif
          @if(in_array($tipo,['local','mesa']))<select name="local"><option value="">Todos los locales</option>@foreach($locales as $l)<option value="{{ $l->id_local }}" @selected((string)$local===(string)$l->id_local)>{{ $l->nombre }}</option>@endforeach</select>@endif
          @if($tipo==='mesa')<select name="mesa"><option value="">Todas las mesas</option>@foreach($mesas as $m)<option value="{{ $m->id_mesa }}" @selected((string)$mesa===(string)$m->id_mesa)>{{ $m->numero_mesa }}</option>@endforeach</select>@endif
          <select name="estado"><option value="">Todos</option><option value="1" @selected($estado==='1')>Activos</option><option value="0" @selected($estado==='0')>Inactivos</option></select>
          <button class="btn btn-primary">Buscar</button><a class="clear-filter" href="{{ route('personeros.index',['tipo'=>$tipo]) }}">Limpiar</a>
        </div>
      </form>
    @endif

    <div class="table-wrap">
      <table class="data-table">
        <thead><tr>
          @if($tipo === 'provincia')
            <th>N°</th><th>DNI</th><th>Persona</th><th>Celular</th><th>Región</th><th>Provincia</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @elseif($tipo === 'regional')
            <th>N°</th><th>DNI</th><th>Persona</th><th>Celular</th><th>Región</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @elseif($tipo === 'distrito')
            <th>N°</th><th>DNI</th><th>Persona</th><th>Celular</th><th>Región</th><th>Provincia</th><th>Distrito</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @elseif($tipo === 'local')
            <th>N°</th><th>DNI</th><th>Persona</th><th>Celular</th><th>Región</th><th>Provincia</th><th>Distrito</th><th>Local</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @elseif($tipo === 'mesa')
            <th>N°</th><th>DNI</th><th>Persona</th><th>Celular</th><th>Región</th><th>Provincia</th><th>Distrito</th><th>Local</th><th>Mesa</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @else
            <th>N°</th><th>DNI</th><th>Persona</th><th>Ámbito</th><th>Condición</th><th>Estado</th><th>Acciones</th>
          @endif
        </tr></thead>
        <tbody>
        @forelse($personeros as $i=>$p)
          <tr>
            <td>{{ ($personeros->currentPage()-1)*$personeros->perPage()+$i+1 }}</td>
            <td class="strong">{{ $p->dni }}</td>
            <td>{{ $p->nombres }} {{ $p->apellido_paterno }} {{ $p->apellido_materno }}</td>
            @if($tipo === 'provincia')
              <td>{{ $p->celular ?? '—' }}</td><td>{{ $p->region_nombre ?? '—' }}</td><td>{{ $p->provincia_nombre ?? '—' }}</td>
            @elseif($tipo === 'regional')
              <td>{{ $p->celular ?? '—' }}</td>
              <td>{{ $p->region_nombre ?? '—' }}</td>
            @elseif($tipo === 'distrito')
              <td>{{ $p->celular ?? '—' }}</td><td>{{ $p->region_nombre ?? '—' }}</td><td>{{ $p->provincia_nombre ?? '—' }}</td><td>{{ $p->distrito_nombre ?? '—' }}</td>
            @elseif($tipo === 'local')
              <td>{{ $p->celular ?? '—' }}</td><td>{{ $p->region_nombre ?? '—' }}</td><td>{{ $p->provincia_nombre ?? '—' }}</td><td>{{ $p->distrito_nombre ?? '—' }}</td><td>{{ $p->local_nombre ?? '—' }}</td>
            @elseif($tipo === 'mesa')
              <td>{{ $p->celular ?? '—' }}</td><td>{{ $p->region_nombre ?? '—' }}</td><td>{{ $p->provincia_nombre ?? '—' }}</td><td>{{ $p->distrito_nombre ?? '—' }}</td><td>{{ $p->local_nombre ?? '—' }}</td><td>{{ isset($p->numero_mesa) ? $p->numero_mesa : '—' }}</td>
            @else
              <td>
                @if(isset($p->region_nombre)){{ $p->region_nombre }}@endif
                @if(isset($p->provincia_nombre)) / {{ $p->provincia_nombre }}@endif
                @if(isset($p->distrito_nombre)) / {{ $p->distrito_nombre }}@endif
                @if(isset($p->local_nombre)) / {{ $p->local_nombre }}@endif
                @if(isset($p->numero_mesa)) / Mesa {{ $p->numero_mesa }}@endif
              </td>
            @endif
            <td class="condition">{{ str_replace('_',' ', $p->condicion) }}</td>
            <td><span class="status {{ $p->estado?'status-active':'status-inactive' }}">{{ $p->estado?'Activo':'Inactivo' }}</span></td>
            <td>
              <button type="button" class="icon-btn edit-personero" title="Editar"
                data-id="{{ $p->{ $tipo==='regional'?'id_personero_regional':($tipo==='provincia'?'id_personero_provincia':($tipo==='distrito'?'id_personero_distrito':($tipo==='local'?'id_personero_local':'id_personero_mesa'))) } }}"
                data-dni="{{ $p->dni }}" data-persona="{{ $p->id_persona }}"
                data-scope="{{ $tipo==='regional'?$p->id_region:($tipo==='provincia'?$p->id_provincia:($tipo==='distrito'?$p->id_distrito:($tipo==='local'?$p->id_local:$p->id_mesa))) }}"
                data-condicion="{{ $p->condicion }}">✎</button>
              <form method="POST" action="{{ route('personeros.toggle',[$tipo,$p->{ $tipo==='regional'?'id_personero_regional':($tipo==='provincia'?'id_personero_provincia':($tipo==='distrito'?'id_personero_distrito':($tipo==='local'?'id_personero_local':'id_personero_mesa'))) }]) }}" style="display:inline">
                @csrf @method('PATCH')
                <button class="icon-btn" title="{{ $p->estado?'Deshabilitar':'Habilitar' }}">{{ $p->estado?'◉':'○' }}</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ in_array($tipo, ['regional','provincia']) ? ($tipo === 'provincia' ? 9 : 8) : ($tipo === 'distrito' ? 10 : (in_array($tipo, ['local','mesa']) ? ($tipo === 'mesa' ? 12 : 11) : 7)) }}" class="empty">No hay personeros registrados en este nivel.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $personeros->withQueryString()->links('pagination.custom') }}
    @endif
  </div>
</div>

<script>
function initPersonerosModule(){
  const tipo=@json($tipo), formBox=document.getElementById('form-personero'), btn=document.getElementById('btnNuevoPersonero');
  const importBox=document.getElementById('import-personero'), importBtn=document.getElementById('btnImportPersonero'), cancelImport=document.getElementById('cancelImportPersonero');
  importBtn?.addEventListener('click',()=>{ importBox?.classList.toggle('hidden'); formBox?.classList.add('hidden'); });
  cancelImport?.addEventListener('click',()=>importBox?.classList.add('hidden'));
  const form=document.getElementById('personeroForm'), method=document.getElementById('personeroMethod'), save=document.getElementById('savePersonero');
  btn?.addEventListener('click',()=>formBox.classList.toggle('hidden'));
  document.getElementById('cancelPersonero')?.addEventListener('click',()=>{
    formBox.classList.add('hidden'); form.reset(); method.value='';
    form.action='{{ route('personeros.store',$tipo) }}';
    document.getElementById('idPersona').value=''; document.getElementById('nombrePersona').value='';
  });
  document.getElementById('buscarPersonero')?.addEventListener('click',async()=>{
    const dni=document.getElementById('dniPersonero').value.trim();
    const msg=document.getElementById('personaMsg');
    if(!dni){msg.textContent='Ingrese un DNI.';return}
    const r=await fetch('{{ url('/personeros/persona') }}/'+encodeURIComponent(dni));
    const d=await r.json();
    if(!d.ok){msg.textContent=d.message;document.getElementById('idPersona').value='';document.getElementById('nombrePersona').value='';return}
    document.getElementById('idPersona').value=d.id_persona;document.getElementById('nombrePersona').value=d.nombre;msg.textContent='Persona encontrada.';
  });
  const region=document.getElementById('pRegion'), prov=document.getElementById('pProvincia'), dist=document.getElementById('pDistrito'), local=document.getElementById('pLocal'), mesa=document.getElementById('pMesa');
  region?.addEventListener('change',async()=>{prov.innerHTML='<option value="">Seleccione</option>';dist&&(dist.innerHTML='<option value="">Seleccione</option>');local&&(local.innerHTML='<option value="">Seleccione</option>');mesa&&(mesa.innerHTML='<option value="">Seleccione</option>'); if(!region.value)return; const d=await fetch('{{ url('/personeros/provincias') }}/'+region.value).then(r=>r.json());d.forEach(x=>prov.insertAdjacentHTML('beforeend',`<option value="${x.id_provincia}">${x.nombre}</option>`));});
  prov?.addEventListener('change',async()=>{dist.innerHTML='<option value="">Seleccione</option>';local&&(local.innerHTML='<option value="">Seleccione</option>');mesa&&(mesa.innerHTML='<option value="">Seleccione</option>');if(!prov.value)return;const d=await fetch('{{ url('/personeros/distritos') }}/'+prov.value).then(r=>r.json());d.forEach(x=>dist.insertAdjacentHTML('beforeend',`<option value="${x.id_distrito}">${x.nombre}</option>`));});
  dist?.addEventListener('change',async()=>{local.innerHTML='<option value="">Seleccione</option>';mesa&&(mesa.innerHTML='<option value="">Seleccione</option>');if(!dist.value)return;const d=await fetch('{{ url('/personeros/locales') }}/'+dist.value).then(r=>r.json());d.forEach(x=>local.insertAdjacentHTML('beforeend',`<option value="${x.id_local}">${x.nombre}</option>`));});
  local?.addEventListener('change',async()=>{if(!mesa)return;mesa.innerHTML='<option value="">Seleccione</option>';if(!local.value)return;const d=await fetch('{{ url('/personeros/mesas') }}/'+local.value).then(r=>r.json());d.forEach(x=>mesa.insertAdjacentHTML('beforeend',`<option value="${x.id_mesa}">Mesa ${x.numero_mesa} — ${x.total_electores} electores</option>`));});

  document.querySelectorAll('.edit-personero').forEach(b=>b.addEventListener('click',()=>{
    formBox.classList.remove('hidden'); form.action='{{ url('/personeros') }}/'+tipo+'/'+b.dataset.id; method.value='PUT';
    document.getElementById('dniPersonero').value=b.dataset.dni; document.getElementById('idPersona').value=b.dataset.persona;
    const cond=form.querySelector('[name="condicion"]'); if(cond)cond.value=b.dataset.condicion;
    save.textContent='Guardar cambios';
    const scope=b.dataset.scope;
    if(tipo==='regional'){region.value=scope;}
    // Para niveles inferiores, cargar la cadena completa no se hace por AJAX de edición;
    // se fuerza al usuario a revisar el ámbito antes de guardar.
    window.scrollTo({top:0,behavior:'smooth'});
  }));
}


if (@json($tipo === 'regional' || $tipo === 'provincia' || $tipo === 'distrito' || $tipo === 'local' || $tipo === 'mesa')) {
  (() => {
    const form = document.getElementById(@json($tipo === 'provincia' ? 'provinciaPersoneroFilter' : ($tipo === 'distrito' ? 'distritoPersoneroFilter' : (in_array($tipo, ['local','mesa']) ? $tipo.'PersoneroFilter' : 'regionalPersoneroFilter'))));
    if (!form) return;

    const fields = form.querySelectorAll('.regional-filter-field');
    const closeMenus = (except = null) => {
      fields.forEach(field => {
        if (field === except) return;
        const menu = field.querySelector('.regional-filter-menu');
        const trigger = field.querySelector('.regional-filter-trigger');
        if (menu) menu.hidden = true;
        if (trigger) trigger.setAttribute('aria-expanded','false');
      });
    };

    fields.forEach(field => {
      const trigger = field.querySelector('.regional-filter-trigger');
      const menu = field.querySelector('.regional-filter-menu');
      const options = field.querySelector('.regional-filter-options');
      const hidden = field.querySelector('input[type="hidden"]');
      const search = field.querySelector('.regional-filter-search');
      if (!trigger || !menu || !options || !hidden) return;

      trigger.addEventListener('click', e => {
        e.preventDefault();
        const opening = menu.hidden;
        closeMenus(field);
        menu.hidden = !opening;
        trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (!opening) return;
        if (search) {
          search.value = '';
          options.querySelectorAll('.regional-filter-option').forEach(o => o.hidden = false);
          requestAnimationFrame(() => search.focus());
        }
        const selected = options.querySelector('.regional-filter-option.is-selected');
        requestAnimationFrame(() => {
          if (selected) {
            // Desplazar únicamente la lista interna; nunca el documento completo.
            const target = selected.offsetTop - (options.clientHeight - selected.offsetHeight) / 2;
            options.scrollTop = Math.max(0, target);
          }
        });
      });

      search?.addEventListener('input', () => {
        const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
        const term = normalize(search.value);
        options.querySelectorAll('.regional-filter-option').forEach(option => {
          option.hidden = term !== '' && !normalize(option.textContent).includes(term);
        });
      });

      options.querySelectorAll('.regional-filter-option').forEach(option => {
        option.addEventListener('click', () => {
          hidden.value = option.dataset.value ?? '';
          const clear = name => { const input = form.querySelector(`input[name="${name}"]`); if (input) input.value = ''; };
          if (field.dataset.filter === 'region') { ['provincia','distrito','local','mesa','estado'].forEach(clear); }
          if (field.dataset.filter === 'provincia') { ['distrito','local','mesa','estado'].forEach(clear); }
          if (field.dataset.filter === 'distrito') { ['local','mesa','estado'].forEach(clear); }
          if (field.dataset.filter === 'local') { ['mesa','estado'].forEach(clear); }
          if (field.dataset.filter === 'mesa') { clear('estado'); }
          options.querySelectorAll('.regional-filter-option').forEach(o => o.classList.remove('is-selected'));
          option.classList.add('is-selected');
          const value = field.querySelector('.regional-filter-value');
          if (value) value.textContent = option.textContent.trim();
          closeMenus();
          form.submit();
        });
      });
    });

    form.querySelector('.regional-personero-search')?.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        form.submit();
      }
    });

    document.addEventListener('click', e => {
      if (!e.target.closest('#regionalPersoneroFilter, #provinciaPersoneroFilter, #distritoPersoneroFilter, #localPersoneroFilter, #mesaPersoneroFilter')) closeMenus();
    });
    const positionMenu = field => {
      const menu = field?.querySelector('.regional-filter-menu'); const trigger = field?.querySelector('.regional-filter-trigger');
      if (!menu || !trigger || menu.hidden) return;
      const r = trigger.getBoundingClientRect(); const width = Math.min(320, window.innerWidth - 24);
      menu.style.width = width + 'px'; menu.style.left = Math.max(12, Math.min(r.left, window.innerWidth - width - 12)) + 'px';
      const desired = Math.min(365, window.innerHeight * .7); const below = window.innerHeight - r.bottom - 12;
      menu.style.maxHeight = Math.max(180, Math.min(desired, below > 190 ? below : r.top - 12)) + 'px';
      menu.style.top = (below > 190 ? r.bottom + 5 : Math.max(12, r.top - Math.min(desired, r.top - 12))) + 'px';
    };
    fields.forEach(field => { field.querySelector('.regional-filter-trigger')?.addEventListener('click', () => requestAnimationFrame(() => positionMenu(field))); });
    window.addEventListener('scroll', () => fields.forEach(positionMenu), {passive:true});
    window.addEventListener('resize', () => fields.forEach(positionMenu), {passive:true});
  })();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPersonerosModule, { once: true });
} else {
  initPersonerosModule();
}
</script>
@endsection
