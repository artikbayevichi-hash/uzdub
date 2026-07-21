/* ============================================================
   js/mini-player.js - Picture-in-Picture & Floating Player
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    const video = document.querySelector('video');
    if (!video) return;

    // Picture-in-Picture tugmasini qo'shish
    if (document.pictureInPictureEnabled) {
        const pipBtn = document.createElement('button');
        pipBtn.innerHTML = '🖼️';
        pipBtn.className = 'pip-button';
        pipBtn.title = 'Kichik oyna';
        
        const controls = document.querySelector('.player-wrap');
        if (controls) controls.appendChild(pipBtn);

        pipBtn.addEventListener('click', async () => {
            try {
                if (video !== document.pictureInPictureElement) {
                    await video.requestPictureInPicture();
                } else {
                    await document.exitPictureInPicture();
                }
            } catch (error) {
                console.error('PiP xatosi:', error);
            }
        });
    }
});
