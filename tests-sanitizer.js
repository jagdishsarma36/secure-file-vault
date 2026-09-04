const fs = require('fs');

// ---------- Minimal-but-faithful DOM shim (subset the sanitizer uses) ----------
const VOID = new Set(['BR','IMG','HR','INPUT','META','LINK','COL']);
function Text(data){ return { nodeType:3, parentNode:null, data:String(data), appendChild(){ throw new Error('no append on text'); }, cloneNode(){ return Text(this.data); } }; }
function El(tag){
  return {
    nodeType:1, tagName: tag.toUpperCase(), parentNode:null,
    attrs: {}, children: [],
    get childNodes(){ return this.children; },
    appendChild(child){ return append(this, child); },
    getAttribute(name){ return (name in this.attrs) ? this.attrs[name] : null; },
    setAttribute(name,val){ this.attrs[name] = String(val); },
    removeAttribute(name){ delete this.attrs[name]; },
    cloneNode(){ return deepClone(this); },
    get innerHTML(){ return serialize(this); },
    set innerHTML(html){ this.children = (this.parentNode ? [] : parseInto(html).children); this.attrs = (this.parentNode ? {} : (this.attrs || {})); }
  };
}
function Fragment(){ return { nodeType:11, parentNode:null, children:[], get childNodes(){ return this.children; }, appendChild(child){ return append(this, child); } }; }
function deepClone(el){
  const c = El(el.tagName);
  c.attrs = Object.assign({}, el.attrs);
  c.children = el.children.map(x => x.cloneNode());
  return c;
}
function append(parent, child){
  if (!child) return child;
  if (child.nodeType === 11) {
    for (const c of child.children.slice()) append(parent, c);
    child.children = [];
    return child;
  }
  child.parentNode = parent;
  parent.children.push(child);
  return child;
}
// Parse html string into a tree of El/Text.
function parseInto(html){
  const root = El('div'); root.children = []; root.attrs = {};
  const stack = [root];
  let i = 0;
  const re = /<(\/?)([a-zA-Z0-9]+)((?:"[^"]*"|'[^']*'|[^>"'])*)>/g;
  let last = 0, m;
  while ((m = re.exec(html))) {
    if (m.index > last) stack[stack.length-1].children.push(Text(html.slice(last, m.index)));
    last = re.lastIndex;
    const closing = m[1] === '/';
    const name = m[2].toUpperCase();
    const rawAttrs = m[3] || '';
    if (!closing) {
      const el = El(name);
      const attrRe = /([a-zA-Z-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/g;
      let am;
      while ((am = attrRe.exec(rawAttrs))) el.attrs[am[1].toLowerCase()] = am[2] || am[3] || am[4];
      append(stack[stack.length-1], el);
      if (!VOID.has(name)) stack.push(el);
    } else {
      // pop up to matching tag
      for (let k = stack.length-1; k > 0; k--) {
        const t = stack[k];
        stack.length = k;
        if (t.tagName === name) break;
      }
    }
  }
  if (last < html.length) stack[stack.length-1].children.push(Text(html.slice(last)));
  return root;
}
function serialize(node){
  if (node.nodeType === 3) return node.data;
  const inner = node.children.map(serialize).join('');
  if (VOID.has(node.tagName)) return '<' + node.tagName.toLowerCase() + '>';
  let attrs = '';
  for (const k of Object.keys(node.attrs)) attrs += ' ' + k + '="' + node.attrs[k] + '"';
  return '<' + node.tagName.toLowerCase() + attrs + '>' + inner + '</' + node.tagName.toLowerCase() + '>';
}

const document = {
  createElement: (t) => El(t),
  createDocumentFragment: () => Fragment(),
  createTextNode: (d) => Text(d),
};

// ---------- Extract the REAL sanitizer + lists from the plugin ----------
const php = fs.readFileSync('/Users/jagdish/Downloads/secure-file-vault/secure-file-vault.php','utf8');
const start = php.indexOf('/* ---------- Formatting (mirrors the server');
const end = php.indexOf('function saveSelection(){');
if (start < 0 || end < 0 || end <= start) { console.error('markers not found'); process.exit(1); }
const js = php.slice(start, end);
eval(js); // defines ALLOWED_TAGS, BLOCK_TAGS, sanitizeStickyHtml

// ---------- Cases ----------
const cases = [
  ['bold tag', '<div><b>hello</b></div><div><b>world</b></div>', '<b>hello</b><br><b>world</b><br>'],
  ['chrome div multiline', '<div>line1</div><div>line2</div>', 'line1<br>line2<br>'],
  ['empty newline div', '<div>a</div><div><br></div><div>b</div>', 'a<br><br>b<br>'],
  ['span bold', '<span style="font-weight: bold;">bold</span>', '<b>bold</b>'],
  ['span weight 700', '<span style="font-weight: 700;">x</span>', '<b>x</b>'],
  ['span weight normal', '<span style="font-weight: normal;">x</span>', 'x'],
  ['span italic', '<span style="font-style: italic;">it</span>', '<i>it</i>'],
  ['span underline', '<span style="text-decoration: underline;">un</span>', '<u>un</u>'],
  ['span strike', '<span style="text-decoration: line-through;">st</span>', '<s>st</s>'],
  ['font color', '<font color="#ff0000">red</font>', '<span style="color:#ff0000">red</span>'],
  ['font style color', '<font style="color: rgb(255,0,0);">x</font>', '<span style="color:rgb(255,0,0)">x</span>'],
  ['span color', '<span style="color: #ff0000;">redspan</span>', '<span style="color:#ff0000">redspan</span>'],
  ['highlight', '<span style="background-color: #fff000;">hl</span>', '<span style="background-color:#fff000">hl</span>'],
  ['empty span', '<span></span>text', 'text'],
  ['nested script in div', '<div><script>alert(1)</script></div>', 'alert(1)<br>'],
  ['direct script', '<script>alert(2)</script>x', 'alert(2)x'],
  ['img', 'a<img src=x onerror=alert(1)>b', 'ab'],
  ['link javascript', '<a href="javascript:alert(3)">x</a>ok', '<a>x</a>ok'],
  ['pre code', '<pre><code>if (a) {\n  return b;\n}</code></pre>', '<pre><code>if (a) {\n  return b;\n}</code></pre>'],
  ['styled b', '<b style="color:red">x</b>', '<b>x</b>'],
  ['p tags', '<p>para1</p><p>para2</p>', '<p>para1</p><p>para2</p>'],
];

let pass = 0, fail = 0;
for (const [name, input, want] of cases) {
  let got;
  try { got = sanitizeStickyHtml(input); }
  catch(e){ got = 'THROW: ' + e.message; }
  const norm = got.replace(/^<div>/,'').replace(/<\/div>$/,'');
  const ok = norm === want;
  if (ok) pass++; else fail++;
  console.log((ok?'PASS':'FAIL') + '  ' + name);
  if (!ok) {
    console.log('   in : ' + JSON.stringify(input));
    console.log('   got: ' + JSON.stringify(norm));
    console.log('   want: ' + JSON.stringify(want));
  }
}
console.log('\n' + pass + ' passed, ' + fail + ' failed');