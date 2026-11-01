const test=require('node:test');
const assert=require('node:assert/strict');
const {Selection,matches}=require('../photo-manager/assets/selection.js');
const cards=[{id:'a',jpeg:9,webp:0,ai:0,pdf:0,bytes:100},{id:'b',jpeg:0,webp:1,ai:0,pdf:0,bytes:0},{id:'c',jpeg:2,webp:3,ai:1,pdf:0,bytes:200}];
test('format selection and actual totals',()=>{const s=new Selection(cards);s.selectVisible('jpeg_only');assert.deepEqual(s.totals(),{photos:1,jpeg:9,bytes:100});s.selectVisible('webp_only');assert.equal(s.totals().photos,2);});
test('manual selection remains available for every format',()=>{const s=new Selection(cards);s.selectVisible('jpeg_only');s.set('c',true);assert.equal(s.totals().photos,2);assert.equal(s.totals().bytes,300);});
test('changing mode and selecting matching cards retains manual choices',()=>{const s=new Selection(cards);s.set('b',true);s.selectVisible('jpeg_webp');assert.deepEqual([...s.selected],['b','c']);s.clear();assert.equal(s.totals().photos,0);});
test('document formats excluded only by strict single-format modes',()=>{assert.equal(matches(cards[2],'jpeg_only'),false);assert.equal(matches(cards[2],'jpeg_webp'),true);assert.equal(matches(cards[1],'webp_only'),true);});
test('unknown identifiers rejected',()=>assert.throws(()=>new Selection(cards).set('unknown',true)));
