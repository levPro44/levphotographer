(function(root) {
  'use strict';
  function matches(c,mode) {
    if(mode==='jpeg_only') return c.jpeg>0 && c.webp===0 && c.ai===0 && c.pdf===0;
    if(mode==='webp_only') return c.webp>0 && c.jpeg===0 && c.ai===0 && c.pdf===0;
    if(mode==='jpeg_webp') return c.jpeg>0 && c.webp>0;
    return mode==='all';
  }
  class Selection {
    constructor(cards) { this.cards=cards; this.selected=new Set(); }
    set(id,on) { if(!this.cards.some(c=>c.id===id)) throw Error('Unknown photo'); on ? this.selected.add(id) : this.selected.delete(id); }
    selectVisible(mode) { this.cards.filter(c=>matches(c,mode)).forEach(c=>this.selected.add(c.id)); }
    clear() { this.selected.clear(); }
    totals() { return this.cards.filter(c=>this.selected.has(c.id)).reduce((a,c)=>({photos:a.photos+1,jpeg:a.jpeg+c.jpeg,bytes:a.bytes+c.bytes}),{photos:0,jpeg:0,bytes:0}); }
  }
  if(typeof module!=='undefined') module.exports={Selection,matches};
  else root.LevSelection={Selection,matches};
})(typeof window==='undefined'?globalThis:window);
