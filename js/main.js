// Header scroll effekti
window.addEventListener('scroll', function () {
    const header = document.querySelector('.site-header');
    if (!header) return;
    if (window.scrollY > 50) header.classList.add('scrolled');
    else header.classList.remove('scrolled');
});

// Qatorlarni chapga/o'ngga siljitish tugmalari (agar qo'shilsa)
document.querySelectorAll('.row-arrow').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const wrap = btn.closest('.row-wrap').querySelector('.row-scroll');
        const dir = btn.dataset.dir === 'left' ? -1 : 1;
        wrap.scrollBy({ left: dir * 600, behavior: 'smooth' });
    });
});

// Qidiruv formasi enter bosilganda submit
document.querySelectorAll('.search-box input').forEach(function (input) {
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            input.closest('form').submit();
        }
    });
});
