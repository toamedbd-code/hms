import{h as A,c as I,w as L,a as t,t as a,k as w,v as O,x as B,o as p,d as c,F,f as j,n as E,g as S,N as Q,p as q,Q as V}from"./app-1GlCAcAS.js";import{u as k,w as z}from"./xlsx-B7sBd1wv.js";import{_ as G}from"./BackendLayout-Dy9VQLE8.js";import"./DropdownLink-DwX3SRqb.js";const W={class:"w-full p-4 mt-3 bg-white rounded shadow-md"},J={class:"flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded"},K={class:"text-xl font-semibold text-gray-800"},X={class:"flex items-center gap-2"},Y=["href"],Z={class:"grid grid-cols-1 md:grid-cols-4 gap-2 p-3 mt-3 bg-slate-100 rounded"},tt={class:"w-full mt-3 overflow-x-auto"},et={class:"w-full text-sm border border-gray-200"},st={class:"px-3 py-2 border"},rt={class:"px-3 py-2 border"},ot={class:"px-3 py-2 border"},at={class:"px-3 py-2 border text-center"},lt={class:"px-3 py-2 border text-right"},nt={class:"px-3 py-2 border text-right"},dt={class:"px-3 py-2 border text-center"},it={class:"px-3 py-2 border text-center"},pt={key:0,class:"bg-slate-50 font-semibold"},ct={class:"px-3 py-2 border text-center"},ut={class:"px-3 py-2 border text-right"},yt={class:"px-3 py-2 border text-right"},mt={key:1},ht={key:0,class:"grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center"},xt={class:"text-sm text-gray-600 text-center md:text-left"},gt={class:"flex items-center justify-center md:justify-end gap-2"},bt=["href"],_t=["innerHTML"],ft=["innerHTML"],Nt={__name:"Index",props:{pageTitle:{type:String,default:"Pharmacy Stock Report"},items:{type:Object,default:()=>({data:[]})},summary:{type:Object,default:()=>({})},filters:{type:Object,default:()=>({})}},setup(N){var $,T,M,C;const D=V(),r=N,h=A({name:(($=r.filters)==null?void 0:$.name)??"",status:((T=r.filters)==null?void 0:T.status)??"",per_page:Number(((M=r.filters)==null?void 0:M.per_page)??((C=r.items)==null?void 0:C.per_page)??20)}),_=q(()=>{var o;return((o=r.items)==null?void 0:o.data)??[]}),H=q(()=>{var o,e;return((e=(o=D.props)==null?void 0:o.webSetting)==null?void 0:e.company_name)||""}),f=()=>{Q.get(route("backend.pharmacy.stock.report"),h.value,{preserveState:!0,preserveScroll:!0,replace:!0})},u=o=>Number(o??0).toFixed(2),v=o=>{if(!o)return"-";const e=String(o);if(/^\d{4}-\d{2}-\d{2}/.test(e))return e.slice(0,10);const i=new Date(e);return Number.isNaN(i.getTime())?e:i.toISOString().slice(0,10)},P=()=>{var l,y,m;const e=`
    <h2 style="margin:0 0 4px 0; text-align:center;">${H.value||"Pharmacy Stock Report"}</h2>
    <p style="margin:0 0 10px 0; text-align:center; font-size:12px;">Pharmacy Stock Report</p>
  `,i=_.value.map(d=>{var s,b;return`
    <tr>
      <td>${d.medicine_name??"-"}</td>
      <td>${((s=d.category)==null?void 0:s.name)??"-"}</td>
      <td>${((b=d.supplier)==null?void 0:b.name)??"-"}</td>
      <td style="text-align:right;">${Number(d.medicine_quantity??0).toFixed(2)}</td>
      <td style="text-align:right;">${u(d.medicine_unit_purchase_price)}</td>
      <td style="text-align:right;">${u(d.medicine_unit_selling_price)}</td>
      <td style="text-align:center;">${v(d.expiry_date)}</td>
      <td style="text-align:center;">${d.status??"-"}</td>
    </tr>
  `}).join(""),x=`
    <tr style="font-weight:700; background:#f8fafc;">
      <td colspan="3">Grand Total</td>
      <td style="text-align:right;">${Number(((l=r.summary)==null?void 0:l.total_qty)??0).toFixed(2)}</td>
      <td style="text-align:right;">${u((y=r.summary)==null?void 0:y.total_purchase_value)}</td>
      <td style="text-align:right;">${u((m=r.summary)==null?void 0:m.total_selling_value)}</td>
      <td colspan="2"></td>
    </tr>
  `,g=`
    <html>
      <head>
        <title>Pharmacy Stock Report</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 16px; }
          table { width:100%; border-collapse: collapse; font-size:12px; }
          th, td { border:1px solid #d1d5db; padding:6px; }
          th { background:#f3f4f6; }
        </style>
      </head>
      <body>
        ${e}
        <table>
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Category</th>
              <th>Supplier</th>
              <th>Qty</th>
              <th>Unit Buy</th>
              <th>Unit Sell</th>
              <th>Expiry</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${i}
            ${x}
          </tbody>
        </table>
      </body>
    </html>
  `,n=window.open("","_blank","width=1100,height=750");n&&(n.document.open(),n.document.write(g),n.document.close(),n.focus(),n.print())},R=()=>{var x,g,n;const o=_.value.map(l=>{var y,m;return{Medicine:l.medicine_name??"-",Category:((y=l.category)==null?void 0:y.name)??"-",Supplier:((m=l.supplier)==null?void 0:m.name)??"-",Qty:Number(l.medicine_quantity??0),UnitBuy:Number(l.medicine_unit_purchase_price??0),UnitSell:Number(l.medicine_unit_selling_price??0),Expiry:v(l.expiry_date),Status:l.status??"-"}});o.push({Medicine:"Grand Total",Category:"",Supplier:"",Qty:Number(((x=r.summary)==null?void 0:x.total_qty)??0),UnitBuy:Number(((g=r.summary)==null?void 0:g.total_purchase_value)??0),UnitSell:Number(((n=r.summary)==null?void 0:n.total_selling_value)??0),Expiry:"",Status:""});const e=k.json_to_sheet(o),i=k.book_new();k.book_append_sheet(i,e,"PharmacyStock"),z(i,"pharmacy-stock-report.xlsx")};return(o,e)=>(p(),I(G,null,{default:L(()=>{var i,x,g,n,l,y,m,d;return[t("div",W,[t("div",J,[t("h1",K,a(N.pageTitle),1),t("div",X,[t("button",{type:"button",class:"px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700",onClick:P},"Print"),t("button",{type:"button",class:"px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700",onClick:R},"Excel"),t("a",{href:o.route("backend.dashboard"),class:"px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700"},"Back Dashboard",8,Y)])]),t("div",Z,[t("div",null,[w(t("input",{"onUpdate:modelValue":e[0]||(e[0]=s=>h.value.name=s),type:"text",placeholder:"Medicine name",class:"w-full p-2 text-sm border rounded",onInput:f},null,544),[[O,h.value.name]])]),t("div",null,[w(t("select",{"onUpdate:modelValue":e[1]||(e[1]=s=>h.value.status=s),class:"w-full p-2 text-sm border rounded",onChange:f},[...e[3]||(e[3]=[t("option",{value:""},"All Status",-1),t("option",{value:"Active"},"Active",-1),t("option",{value:"Inactive"},"Inactive",-1)])],544),[[B,h.value.status]])]),t("div",null,[w(t("select",{"onUpdate:modelValue":e[2]||(e[2]=s=>h.value.per_page=s),class:"w-full p-2 text-sm border rounded",onChange:f},[...e[4]||(e[4]=[t("option",{value:10},"Show 10",-1),t("option",{value:20},"Show 20",-1),t("option",{value:50},"Show 50",-1),t("option",{value:100},"Show 100",-1)])],544),[[B,h.value.per_page]])])]),t("div",tt,[t("table",et,[e[8]||(e[8]=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{class:"px-3 py-2 border"},"Medicine"),t("th",{class:"px-3 py-2 border"},"Category"),t("th",{class:"px-3 py-2 border"},"Supplier"),t("th",{class:"px-3 py-2 border"},"Qty"),t("th",{class:"px-3 py-2 border"},"Unit Buy"),t("th",{class:"px-3 py-2 border"},"Unit Sell"),t("th",{class:"px-3 py-2 border"},"Expiry"),t("th",{class:"px-3 py-2 border"},"Status")])],-1)),t("tbody",null,[(p(!0),c(F,null,j(_.value,s=>{var b,U;return p(),c("tr",{key:s.id,class:"hover:bg-gray-50"},[t("td",st,a(s.medicine_name),1),t("td",rt,a(((b=s.category)==null?void 0:b.name)||"-"),1),t("td",ot,a(((U=s.supplier)==null?void 0:U.name)||"-"),1),t("td",at,a(Number(s.medicine_quantity??0).toFixed(2)),1),t("td",lt,a(u(s.medicine_unit_purchase_price)),1),t("td",nt,a(u(s.medicine_unit_selling_price)),1),t("td",dt,a(v(s.expiry_date)),1),t("td",it,[t("span",{class:E(["px-2 py-1 text-xs rounded",s.status==="Active"?"bg-emerald-100 text-emerald-700":"bg-rose-100 text-rose-700"])},a(s.status),3)])])}),128)),_.value.length>0?(p(),c("tr",pt,[e[5]||(e[5]=t("td",{class:"px-3 py-2 border",colspan:"3"},"Grand Total",-1)),t("td",ct,a(Number(((i=r.summary)==null?void 0:i.total_qty)??0).toFixed(2)),1),t("td",ut,a(u((x=r.summary)==null?void 0:x.total_purchase_value)),1),t("td",yt,a(u((g=r.summary)==null?void 0:g.total_selling_value)),1),e[6]||(e[6]=t("td",{class:"px-3 py-2 border",colspan:"2"},null,-1))])):S("",!0),_.value.length===0?(p(),c("tr",mt,[...e[7]||(e[7]=[t("td",{colspan:"8",class:"px-3 py-6 text-center text-gray-500 border"},"No pharmacy stock found.",-1)])])):S("",!0)])])]),(l=(n=r.items)==null?void 0:n.links)!=null&&l.length?(p(),c("div",ht,[t("p",xt," Displaying "+a(((y=r.items)==null?void 0:y.from)??0)+" to "+a(((m=r.items)==null?void 0:m.to)??0)+" of "+a(((d=r.items)==null?void 0:d.total)??0)+" items ",1),t("nav",null,[t("ul",gt,[(p(!0),c(F,null,j(r.items.links,(s,b)=>(p(),c("li",{key:`${b}-${s.label}`},[s.url?(p(),c("a",{key:0,href:s.url,class:E(["px-3 py-1 text-sm border rounded",s.active?"bg-blue-600 text-white border-blue-600":"hover:bg-gray-100 border-gray-300"])},[t("span",{innerHTML:s.label},null,8,_t)],10,bt)):(p(),c("span",{key:1,class:"px-3 py-1 text-sm text-gray-400 border border-gray-200 rounded",innerHTML:s.label},null,8,ft))]))),128))])])])):S("",!0)])]}),_:1}))}};export{Nt as default};
