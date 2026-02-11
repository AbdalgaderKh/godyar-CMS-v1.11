// assets/js/modules/article_media_fix.js
// Professional MediaFix v20260211
(function(){
  function isArticlePage(){
    var p = (location.pathname || '');
    return /\/news\/id\/\d+/.test(p) || /\/news\//.test(p) || /\/ar\/news\//.test(p) || /\/en\/news\//.test(p) || /\/fr\/news\//.test(p);
  }

  function clampHero(el){
    if(!el) return;
    el.style.maxHeight = '520px';
    el.style.overflow = 'hidden';
  }

  function fixImg(img){
    if(!img) return;
    // Skip small icons/logos
    if(img.closest('.brand-logo') || img.closest('header') || img.closest('.gdy-footer')) return;

    // If inside ratio, ensure cover
    var ratio = img.closest('.ratio');
    if(ratio){
      ratio.style.overflow = 'hidden';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      return;
    }

    // If looks like a hero/cover image (first big image in main)
    var main = document.getElementById('mainContent') || document.querySelector('main');
    if(main && main.contains(img)){
      // Remove width/height attributes if any
      try{ img.removeAttribute('width'); img.removeAttribute('height'); }catch(e){}
      // Remove inline width/height rules by overwriting
      img.style.maxWidth = '100%';
      img.style.height = 'auto';
      // If the image is extremely tall, cap it
      var rect = img.getBoundingClientRect();
      if(rect && rect.height > 700){
        img.style.width = '100%';
        img.style.maxHeight = '520px';
        img.style.objectFit = 'cover';
        var parent = img.parentElement;
        if(parent){
          parent.style.overflow = 'hidden';
          parent.style.maxHeight = '520px';
        }
      }
    }
  }

  function run(){
    if(!isArticlePage()) return;

    // Try common hero wrappers
    var hero = document.querySelector('.article-cover-inner, .article-hero, .news-hero, .gdy-article-cover');
    if(hero) clampHero(hero);

    // Fix all images inside main content
    var main = document.getElementById('mainContent') || document.querySelector('main');
    if(!main) return;
    var imgs = main.querySelectorAll('img');
    imgs.forEach(fixImg);
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
