const test = require('node:test');
const assert = require('node:assert/strict');
const Core = require('../assets/relationship-core.js');
const example = [
  {id:1,full_name:'محمد',father_id:3,mother_id:4},
  {id:2,full_name:'شيروان',father_id:9,mother_id:12},
  {id:3,full_name:'خالد',father_id:5},
  {id:4,full_name:'عائشة',father_id:6},
  {id:5,full_name:'حسن',father_id:7},
  {id:6,full_name:'سليمان',father_id:8},
  {id:7,full_name:'حسين'}, {id:8,full_name:'أبو بكر'},
  {id:9,full_name:'سعيد',father_id:10},
  {id:10,full_name:'حسن',father_id:11}, {id:11,full_name:'حسن'},
  {id:12,full_name:'شيماء',father_id:13},
  {id:13,full_name:'جميل',father_id:14}, {id:14,full_name:'عمر',father_id:7}
];
const fourWays = [
  {id:1,full_name:'الأول',father_id:3,mother_id:4},
  {id:2,full_name:'الثاني',father_id:5,mother_id:6},
  {id:3,mother_id:7}, {id:4,father_id:9},
  {id:5,mother_id:8}, {id:6,father_id:9},
  {id:7,father_id:9}, {id:8,father_id:9}, {id:9,full_name:'الجد المشترك'}
];
function all(rows, options={}) {
  return Core.findAllPathsBetween(1,2,rows,[],'parents','ar',{perType:100000,workBudget:2000000,...options});
}
test('exact Muhammad and Shirwan example follows Shaima, Jamil and Omar to Hussein',()=>{
  const paths=all(example);
  assert.equal(paths.complete,true); assert.equal(paths.length,1);
  assert.deepEqual(paths[0].steps.map(step=>step.id),['1','3','5','7','14','13','12','2']);
  assert.equal(paths[0].commonAncestor.name,'حسين'); assert.equal(paths[0].originCategory,'fm');
  assert.equal(paths[0].ancestorIndex,3); assert.equal(paths[0].stepsCount,7);
  assert(!paths.some(path=>['5','10','11'].includes(path.commonAncestor.id)),'Repeated Hassan names are not merged');
});
test('all four starting combinations include changes between father and mother in later generations',()=>{
  const paths=all(fourWays);
  assert.equal(paths.complete,true); assert.equal(paths.length,4);
  assert.deepEqual(new Set(paths.map(path=>path.originCategory)),new Set(['ff','fm','mf','mm']));
  const ff=paths.find(path=>path.originCategory==='ff');
  assert.equal(ff.pathType,'female');
  assert.deepEqual(ff.steps.map(step=>step.id),['1','3','7','9','8','5','2']);
});
test('does not stop at the first or nearest common ancestor when another independent branch meets higher',()=>{
  const rows=fourWays.map(row=>({...row}));
  rows.find(row=>row.id===4).mother_id=11;
  rows.find(row=>row.id===9).father_id=10;
  rows.push({id:10,full_name:'الأصل الأعلى'},{id:11,father_id:10});
  const paths=all(rows);
  assert.equal(paths.complete,true);
  assert.equal(paths.filter(path=>path.commonAncestor.id==='9').length,4);
  assert.equal(paths.filter(path=>path.commonAncestor.id==='10').length,2);
  for(const path of paths) assert.equal(new Set(path.steps.map(step=>step.id)).size,path.steps.length);
});
test('does not loop above a meeting point to invent extra routes to its ancestors',()=>{
  const rows=fourWays.map(row=>({...row})); rows.find(row=>row.id===9).father_id=10; rows.push({id:10});
  const paths=all(rows); assert.equal(paths.length,4);
  assert.deepEqual(paths.ancestors.map(item=>item.person.id),['9']);
});
test('finds routes beyond eight links without a default generation cutoff',()=>{
  const rows=[{id:1,father_id:3},{id:2,mother_id:9}];
  for(let id=3;id<=8;id++)rows.push({id,father_id:id===8?20:id+1});
  for(let id=9;id<=14;id++)rows.push({id,father_id:id===14?20:id+1});
  rows.push({id:20}); const paths=all(rows);
  assert.equal(paths.complete,true);assert.equal(paths.length,1);assert.equal(paths[0].stepsCount,14);
  assert.equal(all(rows,{maxDepth:8}).length,0);
});
test('resumable pages enumerate every combination to the same ancestor once',()=>{
  const rows=[{id:1,father_id:3,mother_id:4},{id:2,father_id:9,mother_id:10},
    {id:3,father_id:5,mother_id:6},{id:4,father_id:7,mother_id:8},
    {id:9,father_id:11,mother_id:12},{id:10,father_id:13,mother_id:14},
    ...[5,6,7,8,11,12,13,14].map(id=>({id,father_id:20})),{id:20}];
  const session=Core.createAncestorSearch(1,2,rows,'ar'), targets=Object.fromEntries(session.categories.map(type=>[type,2]));
  let snapshot, collected=[], rounds=0;
  do {
    snapshot=session.advance(targets,12,30);collected.push(...snapshot.paths);
    if(snapshot.pageComplete)for(const category of snapshot.categories)targets[category.type]=category.count+2;
    assert(++rounds<1000);
  }while(!snapshot.complete);
  assert.equal(collected.length,16);assert.equal(new Set(collected.map(path=>path.signature)).size,16);
  assert.equal(snapshot.ancestors[0].count,16);
});
test('a shared child or recorded marriage is not a shared ancestor',()=>{
  const rows=[{id:1},{id:2},{id:3,father_id:1,mother_id:2}];
  assert.equal(all(rows).length,0);
  assert.equal(Core.findAllPathsBetween(1,2,rows,[{husband_id:1,wife_id:2}],'parents','ar').length,0);
});
test('same person, direct ancestry and disconnected people have finite exact results',()=>{
  assert.equal(Core.findAllPathsBetween(1,1,example,[],'parents','ar')[0].steps.length,1);
  const direct=Core.findAllPathsBetween(1,7,example,[],'parents','ar');
  assert.equal(direct.length,1);assert.equal(direct[0].originCategory,'direct');assert.equal(direct[0].secondSide,'self');
  assert.equal(all([{id:1},{id:2}]).complete,true);
  assert.throws(()=>Core.createAncestorSearch(1,999,example,'ar'));
});
test('distinct father and mother edges to the same recorded person are not silently lost',()=>{
  const rows=[{id:1,father_id:3,mother_id:3},{id:2,father_id:3},{id:3}];
  const paths=all(rows);assert.equal(paths.length,2);assert.equal(new Set(paths.map(path=>path.signature)).size,2);
});
// Independent oracle: enumerate ascending branches on both sides, then join
// only pairs whose node sets intersect at exactly their common endpoint.
function oracle(rows){
  const map=Object.fromEntries(rows.map(row=>[String(row.id),row]));
  function branches(root){
    const found=new Map();
    function walk(ids,keys){
      const id=ids.at(-1);if(!found.has(id))found.set(id,[]);found.get(id).push({ids,keys});
      for(const kind of ['father','mother']){
        const parent=String(map[id][kind+'_id']);
        if(map[parent]&&!ids.includes(parent))walk([...ids,parent],[...keys,kind+':'+id+':'+parent]);
      }
    }
    walk([root],[]);return found;
  }
  const a=branches('1'),b=branches('2'), result=new Set();
  for(const [ancestor,left]of a)for(const l of left)for(const r of b.get(ancestor)||[]){
    const ids=[...l.ids,...r.ids.slice(0,-1).reverse()],keys=[...l.keys,...r.keys.slice().reverse()];
    if(new Set(ids).size!==ids.length)continue;
    result.add(ids.map((id,i)=>i?keys[i-1]+'>'+id:id).join('|'));
  }
  return result;
}
test('exhaustive ancestor pairs agree with an independent oracle on 60 random pedigrees including cycles',()=>{
  let seed=19743;const rng=()=>{seed=(Math.imul(seed,1664525)+1013904223)>>>0;return seed/4294967296};
  for(let round=0;round<60;round++){
    const rows=Array.from({length:7},(_,i)=>({id:i+1,father_id:rng()<.7?Math.floor(rng()*7)+1:null,mother_id:rng()<.6?Math.floor(rng()*7)+1:null}));
    const paths=all(rows);assert.equal(paths.complete,true);assert.deepEqual(new Set(paths.map(path=>path.signature)),oracle(rows));
  }
});
test('ancestral search handles four thousand generations without recursion',()=>{
  const rows=Array.from({length:4000},(_,i)=>({id:i+1,father_id:i===3999?null:i+2}));
  const paths=Core.findAllPathsBetween(1,4000,rows,[],'parents','ar',{perType:2});
  assert.equal(paths.length,1);assert.equal(paths[0].steps.length,4000);assert.equal(paths.complete,true);
});
module.exports={example,fourWays};
