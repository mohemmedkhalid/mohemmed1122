const test=require('node:test');
const assert=require('node:assert/strict');
const State=require('../assets/relationship-state.js');
function browser(url='https://family.test/relationship.php'){
 const entries=new Map();
 const storage=()=>({getItem:key=>entries.get(key)||null,setItem:(key,value)=>entries.set(key,value)});
 const win={location:new URL(url),localStorage:storage(),sessionStorage:storage(),history:{state:{unrelated:7},replaceState(value,unused,url){this.state=value;win.location=new URL(url)}}};
 return win;
}
const sample={first:'1',second:'2',language:'ar',mode:'parents',depth:8,category:'fm',ancestor:'20',route:'1|father:1:3>3',view:'chain',speed:2,paused:true,hasResults:true,complete:false,counts:{'ancestor:20:fm':6},scroll:720,diagrams:{'route-a':{scale:1.2,left:12,top:80}}};
test('stores only state and restores selections, filters, progress and presentation',()=>{
 const win=browser(),store=State.create(win,'account1');store.save(sample);
 assert.equal(win.history.state.unrelated,7);
 const state=store.read('ku');for(const key of Object.keys(sample))assert.deepEqual(state[key],sample[key]);
 assert(win.location.search.includes('first=1'));assert(!JSON.stringify(state).includes('people'));
});
test('history entry wins over a newer search in another tab',()=>{
 const win=browser(),store=State.create(win,'1');store.save(sample);const entry=win.history.state,oldUrl=win.location.href;
 store.save({...sample,first:'9',second:'10',category:'mm'});win.history.state=entry;win.location=new URL(oldUrl);
 assert.equal(store.read('ku').first,'1');assert.equal(store.read('ku').category,'fm');
});
test('return link with no query restores the tab state',()=>{
 const win=browser(),store=State.create(win,'1');store.save(sample);win.history.state=null;win.location=new URL('https://family.test/relationship.php');
 assert.equal(store.read('ku').route,sample.route);
});
test('a different explicit URL overrides saved people and resets stale filters',()=>{
 const win=browser(),store=State.create(win,'1');store.save(sample);win.location=new URL('https://family.test/relationship.php?first=9&second=10&mode=all&language=ku');
 const state=store.read('ar');assert.equal(state.first,'9');assert.equal(state.second,'10');assert.equal(state.category,'all');assert.equal(state.route,'');assert.deepEqual(state.counts,{});assert.equal(state.language,'ku');
});
test('empty selections and unfinished typing survive reload without reviving old IDs',()=>{
 const win=browser(),store=State.create(win,'1');store.save({...sample,first:'',draftFirst:'محمد ج',hasResults:false});
 const state=store.read('ar');assert.equal(state.first,'');assert.equal(state.draftFirst,'محمد ج');assert.equal(state.hasResults,false);
});
test('a partial deep link does not borrow the other person from an unrelated saved pair',()=>{
 const state=State.resolve(State.clean({...sample,version:1,owner:'1'},'1'),'https://family.test/relationship.php?first=8','1','ar');
 assert.equal(state.first,'8');assert.equal(state.second,'');
});
test('state is separated by signed-in account and application directory',()=>{
 const win=browser(),one=State.create(win,'1');one.save(sample);win.history.state=null;win.location=new URL('https://family.test/relationship.php');
 assert.equal(State.create(win,'2').read('ku').first,'');
 win.location=new URL('https://family.test/other/relationship.php');assert.equal(State.create(win,'1').read('ku').first,'');
});
test('storage errors do not break history navigation',()=>{
 const win=browser();for(const key of ['localStorage','sessionStorage'])Object.defineProperty(win,key,{get(){throw Error('denied')}});
 const store=State.create(win,'1');assert.doesNotThrow(()=>store.save(sample));assert.equal(store.read('ar').first,'1');
});
test('malformed records and unknown versions fall back safely',()=>{
 const win=browser(),store=State.create(win,'1');win.localStorage.setItem(store.key,'{bad json');assert.equal(store.read('ar').first,'');
 win.history.state={relationship:{...sample,version:999,owner:'1'}};assert.equal(store.read('ar').first,'');
 const state=State.clean({...sample,version:1,owner:'1',first:'<script>',category:'bad',speed:99,counts:JSON.parse('{"__proto__":1,"male":4}'),diagrams:JSON.parse('{"__proto__":{"scale":2}}')},'1');
 assert.equal(state.first,'');assert.equal(state.category,'all');assert.equal(state.speed,1);assert.deepEqual(state.counts,{male:4});assert.deepEqual(state.diagrams,{});
});
