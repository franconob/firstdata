/**
 * Created by fherrero on 12/01/15.
 */

if (window.location.pathname !== '/login') {
    var IDLE_TIMEOUT = 300; //seconds
    var _idleSecondsCounter = 0;
    document.onclick = function () {
        _idleSecondsCounter = 0;
    };
    document.onmousemove = function () {
        _idleSecondsCounter = 0;
    };
    document.onkeypress = function () {
        _idleSecondsCounter = 0;
    };

    function CheckIdleTime() {
        _idleSecondsCounter++;
        console.log(_idleSecondsCounter);
        if (_idleSecondsCounter >= IDLE_TIMEOUT) {
            alert("La sesion expiró. Por favor ingrese de nuevo.");
            clearInterval(interval);
            window.location.href = '/';
        }
    }

    var interval = window.setInterval(CheckIdleTime, 1000);


}


