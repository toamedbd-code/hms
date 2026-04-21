import{h as A,c as I,w as L,a as t,t as l,k as w,v as O,s as B,o as p,d as c,F,f as j,n as E,g as S,N as Q,p as q,Q as V}from"./app-C59hudFZ.js";import{u as k,w as z}from"./xlsx-B7sBd1wv.js";import{_ as G}from"./BackendLayout-DN8p92vr.js";import"./DropdownLink-DwGOFbH5.js";const W={class:"w-full p-4 mt-3 bg-white rounded shadow-md"},J={class:"flex flex-wrap items-center justify-between gap-2 p-4 bg-gray-100 rounded"},K={class:"text-xl font-semibold text-gray-800"},X={class:"flex items-center gap-2"},Y=["href"],Z={class:"grid grid-cols-1 md:grid-cols-4 gap-2 p-3 mt-3 bg-slate-100 rounded"},tt={class:"w-full mt-3 overflow-x-auto"},et={class:"w-full text-sm border border-gray-200"},st={class:"px-3 py-2 border"},rt={class:"px-3 py-2 border"},ot={class:"px-3 py-2 border"},at={class:"px-3 py-2 border text-center"},lt={class:"px-3 py-2 border text-right"},nt={class:"px-3 py-2 border text-right"},dt={class:"px-3 py-2 border text-center"},it={class:"px-3 py-2 border text-center"},pt={key:0,class:"bg-slate-50 font-semibold"},ct={class:"px-3 py-2 border text-center"},ut={class:"px-3 py-2 border text-right"},yt={class:"px-3 py-2 border text-right"},mt={key:1},ht={key:0,class:"grid grid-cols-1 gap-4 pt-3 my-2 md:grid-cols-2 items-center"},xt={class:"text-sm text-gray-600 text-center md:text-left"},bt={class:"flex items-center justify-center md:justify-end gap-2"},gt=["href"],_t=["innerHTML"],ft=["innerHTML"],Nt={__name:"Index",props:{pageTitle:{type:String,default:"Pharmacy Stock Report"},items:{type:Object,default:()=>({data:[]})},summary:{type:Object,default:()=>({})},filters:{type:Object,default:()=>({})}},setup(N){var $,T,M,C;const D=V(),r=N,h=A({name:(($=r.filters)==null?void 0:$.name)??"",status:((T=r.filters)==null?void 0:T.status)??"",per_page:Number(((M=r.filters)==null?void 0:M.per_page)??((C=r.items)==null?void 0:C.per_page)??20)}),_=q(()=>{var a;return((a=r.items)==null?void 0:a.data)??[]}),H=q(()=>{var a,e;return((e=(a=D.props)==null?void 0:a.webSetting)==null?void 0:e.company_name)||""}),f=()=>{Q.get(route("backend.pharmacy.stock.report"),h.value,{preserveState:!0,preserveScroll:!0,replace:!0})},u=a=>Number(a??0).toFixed(2),v=a=>{if(!a)return"-";const e=String(a);if(/^\d{4}-\d{2}-\d{2}/.test(e))return e.slice(0,10);const i=new Date(e);return Number.isNaN(i.getTime())?e:i.toISOString().slice(0,10)},P=()=>{var n,y,m;const e=`
    <h2 style="margin:0 0 4px 0; text-align:center;">${H.value||"Pharmacy Stock Report"}</h2>
    <p style="margin:0 0 10px 0; text-align:center; font-size:12px;">Pharmacy Stock Report</p>
  `,i=_.value.map(o=>{var s,g;return`
    <tr>
      <td>${o.medicine_name??"-"}</td>
      <td>${((s=o.category)==null?void 0:s.name)??"-"}</td>
      <td>${((g=o.supplier)==null?void 0:g.name)??"-"}</td>
      <td style="text-align:right;">${Number(o.medicine_quantity??0).toFixed(2)}</td>
      <td style="text-align:right;">${u(o.medicine_unit_purchase_price)}</td>
      <td style="text-align:right;">${u(o.medicine_unit_selling_price)}</td>
      <td style="text-align:center;">${v(o.expiry_date)}</td>
      <td style="text-align:center;">${o.status??"-"}</td>
    </tr>
  `}).join(""),x=`
    <tr style="font-weight:700; background:#f8fafc;">
      <td colspan="3">Grand Total</td>
      <td style="text-align:right;">${Number(((n=r.summary)==null?void 0:n.total_qty)??0).toFixed(2)}</td>
      <td style="text-align:right;">${u((y=r.summary)==null?void 0:y.total_purchase_value)}</td>
      <td style="text-align:right;">${u((m=r.summary)==null?void 0:m.total_selling_value)}</td>
      <td colspan="2"></td>
    </tr>
  `,b=`
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
  `,d=(()=>{try{const o=window.open("","_blank");try{o&&(o.opener=null)}catch{}return o}catch{return null}})();if(d){d.document.open(),d.document.write(b),d.document.close();try{d.focus()}catch{}try{d.print()}catch{}}},R=()=>{var x,b,d;const a=_.value.map(n=>{var y,m;return{Medicine:n.medicine_name??"-",Category:((y=n.category)==null?void 0:y.name)??"-",Supplier:((m=n.supplier)==null?void 0:m.name)??"-",Qty:Number(n.medicine_quantity??0),UnitBuy:Number(n.medicine_unit_purchase_price??0),UnitSell:Number(n.medicine_unit_selling_price??0),Expiry:v(n.expiry_date),Status:n.status??"-"}});a.push({Medicine:"Grand Total",Category:"",Supplier:"",Qty:Number(((x=r.summary)==null?void 0:x.total_qty)??0),UnitBuy:Number(((b=r.summary)==null?void 0:b.total_purchase_value)??0),UnitSell:Number(((d=r.summary)==null?void 0:d.total_selling_value)??0),Expiry:"",Status:""});const e=k.json_to_sheet(a),i=k.book_new();k.book_append_sheet(i,e,"PharmacyStock"),z(i,"pharmacy-stock-report.xlsx")};return(a,e)=>(p(),I(G,null,{default:L(()=>{var i,x,b,d,n,y,m,o;return[t("div",W,[t("div",J,[t("h1",K,l(N.pageTitle),1),t("div",X,[t("button",{type:"button",class:"px-3 py-2 text-sm font-semibold text-white bg-sky-600 rounded hover:bg-sky-700",onClick:P},"Print"),t("button",{type:"button",class:"px-3 py-2 text-sm font-semibold text-white bg-emerald-600 rounded hover:bg-emerald-700",onClick:R},"Excel"),t("a",{href:a.route("backend.dashboard"),class:"px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded hover:bg-gray-700"},"Back Dashboard",8,Y)])]),t("div",Z,[t("div",null,[w(t("input",{"onUpdate:modelValue":e[0]||(e[0]=s=>h.value.name=s),type:"text",placeholder:"Medicine name",class:"w-full p-2 text-sm border rounded",onInput:f},null,544),[[O,h.value.name]])]),t("div",null,[w(t("select",{"onUpdate:modelValue":e[1]||(e[1]=s=>h.value.status=s),class:"w-full p-2 text-sm border rounded",onChange:f},[...e[3]||(e[3]=[t("option",{value:""},"All Status",-1),t("option",{value:"Active"},"Active",-1),t("option",{value:"Inactive"},"Inactive",-1)])],544),[[B,h.value.status]])]),t("div",null,[w(t("select",{"onUpdate:modelValue":e[2]||(e[2]=s=>h.value.per_page=s),class:"w-full p-2 text-sm border rounded",onChange:f},[...e[4]||(e[4]=[t("option",{value:10},"Show 10",-1),t("option",{value:20},"Show 20",-1),t("option",{value:50},"Show 50",-1),t("option",{value:100},"Show 100",-1)])],544),[[B,h.value.per_page]])])]),t("div",tt,[t("table",et,[e[8]||(e[8]=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{class:"px-3 py-2 border"},"Medicine"),t("th",{class:"px-3 py-2 border"},"Category"),t("th",{class:"px-3 py-2 border"},"Supplier"),t("th",{class:"px-3 py-2 border"},"Qty"),t("th",{class:"px-3 py-2 border"},"Unit Buy"),t("th",{class:"px-3 py-2 border"},"Unit Sell"),t("th",{class:"px-3 py-2 border"},"Expiry"),t("th",{class:"px-3 py-2 border"},"Status")])],-1)),t("tbody",null,[(p(!0),c(F,null,j(_.value,s=>{var g,U;return p(),c("tr",{key:s.id,class:"hover:bg-gray-50"},[t("td",st,l(s.medicine_name),1),t("td",rt,l(((g=s.category)==null?void 0:g.name)||"-"),1),t("td",ot,l(((U=s.supplier)==null?void 0:U.name)||"-"),1),t("td",at,l(Number(s.medicine_quantity??0).toFixed(2)),1),t("td",lt,l(u(s.medicine_unit_purchase_price)),1),t("td",nt,l(u(s.medicine_unit_selling_price)),1),t("td",dt,l(v(s.expiry_date)),1),t("td",it,[t("span",{class:E(["px-2 py-1 text-xs rounded",s.status==="Active"?"bg-emerald-100 text-emerald-700":"bg-rose-100 text-rose-700"])},l(s.status),3)])])}),128)),_.value.length>0?(p(),c("tr",pt,[e[5]||(e[5]=t("td",{class:"px-3 py-2 border",colspan:"3"},"Grand Total",-1)),t("td",ct,l(Number(((i=r.summary)==null?void 0:i.total_qty)??0).toFixed(2)),1),t("td",ut,l(u((x=r.summary)==null?void 0:x.total_purchase_value)),1),t("td",yt,l(u((b=r.summary)==null?void 0:b.total_selling_value)),1),e[6]||(e[6]=t("td",{class:"px-3 py-2 border",colspan:"2"},null,-1))])):S("",!0),_.value.length===0?(p(),c("tr",mt,[...e[7]||(e[7]=[t("td",{colspan:"8",class:"px-3 py-6 text-center text-gray-500 border"},"No pharmacy stock found.",-1)])])):S("",!0)])])]),(n=(d=r.items)==null?void 0:d.links)!=null&&n.length?(p(),c("div",ht,[t("p",xt," Displaying "+l(((y=r.items)==null?void 0:y.from)??0)+" to "+l(((m=r.items)==null?void 0:m.to)??0)+" of "+l(((o=r.items)==null?void 0:o.total)??0)+" items ",1),t("nav",null,[t("ul",bt,[(p(!0),c(F,null,j(r.items.links,(s,g)=>(p(),c("li",{key:`${g}-${s.label}`},[s.url?(p(),c("a",{key:0,href:s.url,class:E(["px-3 py-1 text-sm border rounded",s.active?"bg-blue-600 text-white border-blue-600":"hover:bg-gray-100 border-gray-300"])},[t("span",{innerHTML:s.label},null,8,_t)],10,gt)):(p(),c("span",{key:1,class:"px-3 py-1 text-sm text-gray-400 border border-gray-200 rounded",innerHTML:s.label},null,8,ft))]))),128))])])])):S("",!0)])]}),_:1}))}};export{Nt as default};
