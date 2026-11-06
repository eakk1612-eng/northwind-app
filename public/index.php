<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Northwind – จัดการสินค้า</title>
<style>
  :root { --bg:#0f172a; --card:#1e293b; --line:#334155; --txt:#e2e8f0; --mut:#94a3b8; --pri:#38bdf8; --ok:#22c55e; --err:#ef4444; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,"Sarabun","Noto Sans Thai",sans-serif; background:var(--bg); color:var(--txt); }
  .wrap { max-width:1000px; margin:0 auto; padding:24px 16px 60px; }
  h1 { margin:0 0 4px; } .sub { color:var(--mut); margin-bottom:20px; }
  .bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
  input { background:#0b1220; border:1px solid var(--line); color:var(--txt); border-radius:8px; padding:10px 12px; font-size:15px; width:100%; }
  input:focus { outline:2px solid var(--pri); }
  .bar input { flex:1; min-width:200px; width:auto; }
  button { border:0; border-radius:8px; padding:10px 16px; font-size:15px; cursor:pointer; color:#fff; background:#475569; }
  button.pri { background:var(--pri); color:#06202e; font-weight:600; }
  button.del { background:var(--err); } button.edit { background:#f59e0b; color:#201400; }
  button.sm { padding:6px 12px; font-size:13px; }
  .tbl { background:var(--card); border:1px solid var(--line); border-radius:12px; overflow:auto; }
  table { width:100%; border-collapse:collapse; min-width:620px; }
  th,td { padding:10px 14px; text-align:left; border-bottom:1px solid var(--line); }
  th { color:var(--mut); font-weight:600; font-size:13px; } td.r, th.r { text-align:right; }
  tr:last-child td { border-bottom:0; }
  .empty { padding:30px; text-align:center; color:var(--mut); }
  .modal { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; padding:16px; }
  .modal.show { display:flex; }
  .box { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; width:100%; max-width:440px; }
  .box h2 { margin-top:0; } label { display:block; margin:12px 0 4px; color:var(--mut); font-size:14px; }
  .fe { color:var(--err); font-size:13px; min-height:16px; }
  .acts { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
  #toast { position:fixed; top:16px; right:16px; padding:12px 18px; border-radius:10px; color:#fff; display:none; max-width:340px; z-index:9; box-shadow:0 6px 20px rgba(0,0,0,.4); }
  #toast.ok { background:var(--ok); } #toast.err { background:var(--err); }
</style>
</head>
<body>
<div class="wrap">
  <h1>จัดการสินค้า (Northwind)</h1>
  <div class="sub">ค้นหา เพิ่ม แก้ไข และลบสินค้า ผ่าน REST API</div>

  <div class="bar">
    <input id="search" placeholder="ค้นหาชื่อสินค้า หรือรหัสสินค้า...">
    <button class="pri" id="btnSearch">ค้นหา</button>
    <button id="btnClear">ล้าง</button>
    <button class="pri" id="btnAdd">+ เพิ่มสินค้า</button>
  </div>

  <div class="tbl">
    <table>
      <thead><tr><th>รหัส</th><th>ชื่อสินค้า</th><th>หน่วย</th><th class="r">ราคา</th><th class="r">คงเหลือ</th><th class="r">จัดการ</th></tr></thead>
      <tbody id="rows"></tbody>
    </table>
    <div class="empty" id="empty" style="display:none">ไม่พบข้อมูลสินค้า</div>
  </div>
</div>

<div class="modal" id="modal">
  <div class="box">
    <h2 id="mTitle">เพิ่มสินค้า</h2>
    <input type="hidden" id="fId">
    <label>ชื่อสินค้า *</label><input id="fName" maxlength="40"><div class="fe" id="eName"></div>
    <label>หน่วย / บรรจุภัณฑ์</label><input id="fUnit" maxlength="20"><div class="fe" id="eUnit"></div>
    <label>ราคา *</label><input id="fPrice" type="number" step="0.01" min="0"><div class="fe" id="ePrice"></div>
    <label>จำนวนคงเหลือ *</label><input id="fStock" type="number" step="1" min="0"><div class="fe" id="eStock"></div>
    <div class="acts"><button id="btnCancel">ยกเลิก</button><button class="pri" id="btnSave">บันทึก</button></div>
  </div>
</div>
<div id="toast"></div>

<script>
const API = 'api/products.php';
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let items = [];

function toast(msg, ok = true) {
  const t = $('toast'); t.textContent = msg; t.className = ok ? 'ok' : 'err'; t.style.display = 'block';
  clearTimeout(toast.t); toast.t = setTimeout(() => t.style.display = 'none', 3500);
}
async function call(method, url, body) {
  const res = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: body ? JSON.stringify(body) : undefined });
  let data; try { data = await res.json(); } catch { data = { ok:false, message:'เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ' }; }
  return data;
}
async function load() {
  const q = $('search').value.trim();
  const r = await call('GET', API + (q ? '?q=' + encodeURIComponent(q) : ''));
  if (!r.ok) { toast(r.message, false); return; }
  items = r.data;
  $('rows').innerHTML = items.map(p => `<tr>
    <td>${esc(p.id)}</td><td>${esc(p.name)}</td><td>${esc(p.unit)}</td>
    <td class="r">${p.price === null ? '-' : Number(p.price).toLocaleString('th-TH',{minimumFractionDigits:2})}</td>
    <td class="r">${esc(p.stock ?? '-')}</td>
    <td class="r"><button class="sm edit" onclick="openForm(${p.id})">แก้ไข</button>
    <button class="sm del" onclick="del(${p.id})">ลบ</button></td></tr>`).join('');
  $('empty').style.display = items.length ? 'none' : 'block';
}
function clearErrors() { ['Name','Unit','Price','Stock'].forEach(k => $('e'+k).textContent = ''); }
function openForm(id) {
  clearErrors();
  const p = id ? items.find(x => x.id == id) : null;
  $('mTitle').textContent = p ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า';
  $('fId').value = p ? p.id : '';
  $('fName').value = p ? p.name : ''; $('fUnit').value = p ? (p.unit ?? '') : '';
  $('fPrice').value = p ? (p.price ?? '') : ''; $('fStock').value = p ? (p.stock ?? '') : '';
  $('modal').classList.add('show'); $('fName').focus();
}
function closeForm() { $('modal').classList.remove('show'); }
function clientValidate(d) {
  const e = {};
  if (!d.name) e.name = 'กรุณากรอกชื่อสินค้า';
  else if (d.name.length > 40) e.name = 'ชื่อสินค้าต้องไม่เกิน 40 ตัวอักษร';
  if (d.price === '' || isNaN(d.price)) e.price = 'ราคาต้องเป็นตัวเลข'; else if (+d.price < 0) e.price = 'ราคาต้องไม่ติดลบ';
  if (d.stock === '' || !Number.isInteger(+d.stock)) e.stock = 'จำนวนคงเหลือต้องเป็นจำนวนเต็ม'; else if (+d.stock < 0) e.stock = 'จำนวนคงเหลือต้องไม่ติดลบ';
  return e;
}
async function save() {
  clearErrors();
  const d = { name:$('fName').value.trim(), unit:$('fUnit').value.trim(), price:$('fPrice').value, stock:$('fStock').value };
  const id = $('fId').value;
  const errs = clientValidate(d);
  if (Object.keys(errs).length) { showErrors(errs); return; }
  const r = id ? await call('PUT', API, { ...d, id }) : await call('POST', API, d);
  if (r.ok) { closeForm(); toast(r.message); load(); }
  else { if (r.errors) showErrors(r.errors); toast(r.message, false); }
}
function showErrors(e) { for (const k in e) { const el = $('e' + k[0].toUpperCase() + k.slice(1)); if (el) el.textContent = e[k]; } }
async function del(id) {
  const p = items.find(x => x.id == id);
  if (!confirm(`ต้องการลบสินค้า "${p ? p.name : id}" ใช่หรือไม่?`)) return;
  const r = await call('DELETE', API + '?id=' + id);
  toast(r.message, r.ok); if (r.ok) load();
}
$('btnAdd').onclick = () => openForm(null);
$('btnCancel').onclick = closeForm; $('btnSave').onclick = save;
$('btnSearch').onclick = load;
$('btnClear').onclick = () => { $('search').value = ''; load(); };
$('search').addEventListener('keydown', e => { if (e.key === 'Enter') load(); });
load();
</script>
</body>
</html>
