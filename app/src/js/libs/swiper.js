/* global d */

d.addEventListener('DOMContentLoaded', () => {

    d.addEventListener('touchstart', touchStart);
    d.addEventListener('touchend', touchEnd);
    d.addEventListener('touchcancel', touchEnd);
    d.addEventListener('touchmove', touchMove);
    
});

function touchStart(e) {
    const touches = e.changedTouches;

    for (let i = 0; i < touches.length; i++) {
        startSwipeX = touches[i].pageX;
        startSwipeY = touches[i].pageY;
    }
}

function touchMove(e) {
    //const touches = e.changedTouches;
    //for (let i = 0; i < touches.length; i++) {
    //    console.log(`Move: ${touches[i].pageX}`);
    //}
}

function touchEnd(e) {
    const touches = e.changedTouches;

    for (let i = 0; i < touches.length; i++) {
        stopSwipeX = touches[i].pageX;
        stopSwipeY = touches[i].pageY;
    }
    
    checkSwipeX();
}
