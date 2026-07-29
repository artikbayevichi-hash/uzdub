(function(){
    var SEEN_KEY='uzdub_splash_seen';

    // Agar allaqachon ko'rilgan bo'lsa — avtomatik redirect
    if(localStorage.getItem(SEEN_KEY)==='1'){
        window.location.href='/uzdub/index.php';
        return;
    }

    // === "Saytga kirish" tugmasi ===
    var enterBtn=document.getElementById('lsEnterBtn');
    if(enterBtn){
        enterBtn.addEventListener('click',function(){
            localStorage.setItem(SEEN_KEY,'1');
            window.location.href='/uzdub/index.php';
        });
    }

    // === Zarrachalar ===
    var pc=document.getElementById('lsParticles');
    if(pc){
        for(var i=0;i<35;i++){
            var d=document.createElement('div');
            d.className='ls-dot';
            var sz=Math.random()*4+2;
            d.style.width=sz+'px';
            d.style.height=sz+'px';
            d.style.left=Math.random()*100+'%';
            d.style.animationDuration=(Math.random()*10+8)+'s';
            d.style.animationDelay=(Math.random()*10)+'s';
            d.style.opacity=Math.random()*.5+.1;
            pc.appendChild(d);
        }
    }

    // === Scroll reveal (feature cards) ===
    var cards=document.querySelectorAll('.ls-feature-card');
    var obs=new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if(e.isIntersecting){
                e.target.classList.add('ls-visible');
                obs.unobserve(e.target);
            }
        });
    },{threshold:.15});
    cards.forEach(function(c){obs.observe(c);});

    // === Fade-up elements ===
    var fadeEls=document.querySelectorAll('.ls-fade-up');
    var fadeObs=new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if(e.isIntersecting){
                e.target.classList.add('ls-visible');
                fadeObs.unobserve(e.target);
            }
        });
    },{threshold:.15});
    fadeEls.forEach(function(el){fadeObs.observe(el);});

    // === Counter animatsiyasi ===
    function animateCounter(el,target,suffix){
        suffix=suffix||'';
        var isFloat=target%1!==0;
        var current=0;
        var step=target/60;
        var timer=setInterval(function(){
            current+=step;
            if(current>=target){current=target;clearInterval(timer);}
            el.textContent=isFloat?current.toFixed(1)+suffix:Math.floor(current)+suffix;
        },25);
    }

    var statsEl=document.querySelector('.ls-stats');
    if(statsEl){
        var statsObs=new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if(e.isIntersecting){
                    animateCounter(document.getElementById('lsStatContent'),500,'+');
                    animateCounter(document.getElementById('lsStatUsers'),10,'K+');
                    animateCounter(document.getElementById('lsStatRating'),4.8,'');
                    statsObs.unobserve(e.target);
                }
            });
        },{threshold:.3});
        statsObs.observe(statsEl);
    }

    // === Smooth scroll (pastga tugma) ===
    var scrollHint=document.querySelector('.ls-scroll-hint');
    if(scrollHint){
        scrollHint.addEventListener('click',function(){
            var target=document.querySelector('.ls-features');
            if(target) target.scrollIntoView({behavior:'smooth'});
        });
    }
})();
