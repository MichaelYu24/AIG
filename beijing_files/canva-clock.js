var Clock = (function() {
        
    // private variables
    var canvas, // canvas element
        ctx, // canvas context
        bgGrad = true, // background gradient flag
        gradient, // gradient (background)
        height = 400, // canvas height
        key = {up: false, shift: false}, // key presses
        particles = [], // particle array
        particleColor = 'hsla(0, 100%, 100%, 1)', // particle color
        mouse = {x: 0, y: 0}, // position of mouse / touch
        press = false, // pressed flag
        quiver = false, // quiver flag
        text, // the text to copy pixels from
        textSize = 140, // (initial) textsize
        valentine = false, // valentine-ify it for a bit?
        msgTime = 100, // time to show a message before returning to clock
        updateColor = true, // update color of gradient / particles with time?
        width = 1000, // canvas width
        spreadFlag = true, // 是否要鼠标放上去点击后构成canvas的数字小圆散开的效果
        timeText = '',
        timeColor= "rgba(220,62,80,0.8)";  //构成canvas的数字小圆的颜色 
        dueTime = '2017-05-10 12:00:00';  //到期时间
    
    // Constants
    var FRAME_RATE = 20, // frames per second target
        MIN_WIDTH = 1000, // minimum width of canvas
        MIN_HEIGHT = 400, // minimum height of canvas
        PARTICLE_NUM = 900, // (max) number of particles to generate
        RADIUS = Math.PI * 2; // radius of particle
    
    var defaultStyles = function() {
        textSize = 140;
        // particle color
        particleColor = 'hsla(0, 100%, 100%, 1)'; 

        // color stops
        var gradientStops = { 
            0: 'transparent',
            0.5: 'transparent'
        };

        // create gradient
        setGradient(gradientStops);
    };
    
    var draw = function(p) {
        ctx.fillStyle = particleColor;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, RADIUS, true);
        ctx.closePath();
        ctx.fill();
    };
    
    var explode = function() {
        for(var i = 0, l = particles.length; i < l; i++) {
            var p = particles[i];

            if(p.inText) {

                var ax = mouse.x - p.px,
                ay = mouse.y - p.py,
                angle = Math.atan2(ay, ax),
                polarity,
                C = Math.cos(angle),
                S = Math.sin(angle);

                // change polarity
                // attract particles if mouse pressed, repel if shift + mousedown
                polarity = (key.shift === true) ? -1 : 1;

                p.x += polarity * (Math.pow((C-1), 2) -1) + p.velocityX * p.delta;
                p.y += polarity * (Math.pow((S-1), 2) -1) + p.velocityY * p.delta;

                // set previous positions
                p.px = p.x;
                p.py = p.y;

                draw(p);
            }
        }
    };

    var getTime = function(amPM) {
        var date = new Date(),
            hours = date.getHours(),
            timeOfDay = '';

        if(amPM) {
            hours = ( hours > 12 ) ? hours -= 12 : hours;
            hours = ( hours == 0 ) ? 12 : hours;
        } else {
            hours = pad(hours);
        }
        var minutes = pad(date.getMinutes());
        var seconds = pad(date.getSeconds());
        timeStrings = countDown(dueTime);
        //console.info(timeText);
        return {
            timeString:timeStrings
        };
    };

    //time_val 比对的时间 格式如2016-12-14 22:55:40
    var countDown =  function(time_val){
            var www_qsyz_net=/^[\d]{4}-[\d]{1,2}-[\d]{1,2}( [\d]{1,2}:[\d]{1,2}(:[\d]{1,2})?)?$/ig,str='',conn,s;
            if(!time_val.match(www_qsyz_net)){
                    //console.info('参数格式为2012-01-01[ 01:01[:01]].\r其中[]内的内容可省略');
                    return false;
            }
            var sec=(new Date(time_val.replace(/-/ig,'/')).getTime() - new Date().getTime())/1000;
            if(sec > 0){
                    conn='';
            }else{
                timeText = '已结束' ;
                $('.day-text').hide();
                return timeText;
            }
            var day = Math.floor(sec/24/3600);
            if(day<10){
                day = ''+day;
            }else if(day<100){
                day = ' '+day;
            }
            var hours = Math.floor(sec/3600%24);
            if(hours <10){
                hours = '0'+hours;
            }
            var minutes = Math.floor(sec/60%60);
            if(minutes <10){
                minutes = '0'+minutes;
            }
            var seconds  = Math.floor(sec%60);
            if(seconds <10){
                seconds = '0'+seconds;
            }
            if(Math.floor(sec)==0){ str='0秒'; }
            timeText = day + '  '+ hours + ':' + minutes+ ':'+ seconds ;
            return timeText;
            //每隔一秒更新
            setTimeout(function(){countDown(time_val)},1000);
    }
    // animation loop
    var loop = function() {
      
        // clear out text
        ctx.clearRect(0, 0, width, height);

        var time = getTime(true);

        textSize = 140;

        // draw text on canvas
        if(valentine === true) {
            if(msgTime > 0) {
                textSize = 180;
                text = '?';
                msgTime--;
            } else {
                text = time.timeString;
            }
            // valentine-ify it by setting hue to pink
            setStyles(0);

        } else if(updateColor === true && bgGrad === true) {
            // changing color with time
            // @TODO: come up with something better, this is a hacky implementation
            //var color = time.hours + time.minutes + time.seconds;
            setStyles(0);
            text = time.timeString;
        } else {
            defaultStyles();
            text = time.timeString;
        }
      
        ctx.fillStyle = "rgb(255, 0, 0)";
        ctx.textBaseline = "middle";
        ctx.font = textSize + "px 'Avenir', 'Helvetica Neue', 'Arial', 'sans-serif'";
        ctx.fillText(text, (width - ctx.measureText(text).width) * 0.5, height * 0.5);

        // copy pixels
        var imgData = ctx.getImageData(0, 0, width, height);
      
        // clear canvas, again
        ctx.clearRect(0, 0, width, height);

        if(bgGrad === true) {
            // draw gradient
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height);
        }

        if(press === false) {
            // reset particles
            for(var i = 0, l = particles.length; i < l; i++) {
                var p = particles[i];
                p.inText = false;
            }
            particleText(imgData);
        } else {
            explode();
        }
        FPS.update('fps');
    };

    var pad = function(number) {
        return ('0' + number).substr(-2);
    };

    var particleText = function(imgData) {

        var pxls = [];
        for(var w = width; w > 0; w-=6) {
            for(var h = 0; h < width; h+=6) {
                var index = (w+h*(width))*4;
                if(imgData.data[index] > 10) {
                    pxls.push([w, h]);
                }
            }
        }

        var count = pxls.length;
        for(var i = 0; i < pxls.length && i < particles.length; i++) {
            try {
                var p = particles[i], 
                    X, 
                    Y;
                
                if(quiver) {
                    X = (pxls[count-1][0]) - (p.px + Math.random() * 5);
                    Y = (pxls[count-1][1]) - (p.py + Math.random() * 5);
                } else {
                    X = (pxls[count-1][0]) - p.px;
                    Y = (pxls[count-1][1]) - p.py;
                }
          
                // tangent
                var T = Math.sqrt(X*X + Y*Y);

                // arctangent
                var A = Math.atan2(Y, X);
              
                // cosine
                var C = Math.cos(A);
              
                // sine
                var S = Math.sin(A);
              
                // set new postition
                p.x = p.px + C * T * p.delta;
                p.y = p.py + S * T * p.delta;
              
                // set previous positions
                p.px = p.x;
                p.py = p.y;
          
                p.inText = true;
          
                // draw the particle
                draw(p);
          
                if(key.up === true) {
                    p.size += 0.3;
                } else {
                    var newSize = p.size - 0.5;
                    if(newSize > p.origSize && newSize > 0) {
                        p.size = newSize;
                    } else {
                        p.size = m.origSize;
                    }
                }
            } catch(e) {
                //console.log(e);
            }
            count--;
        }
    };

    var setCoordinates = function(e) {
        if(e.offsetX) {
            return { x: e.offsetX, y: e.offsetY }; // use offset if available
        } else if (e.layerX) {
            return { x: e.layerX, y: e.layerY }; // firefox... make sure to position the canvas
        } else {
            // iOS. Maybe others too?
            return { x: e.pageX - canvas.offsetLeft, y: e.pageY - canvas.offsetTop };
        }
    };

    // set dimensions of canvas
    var setDimensions = function () {
        width = Math.max(window.innerWidth, MIN_WIDTH);
        height = Math.min(window.innerHeight, MIN_HEIGHT);
        console.info(width);
        // Resize the canvas
        canvas.width = width;
        canvas.height = height;

        canvas.style.position = 'absolute';
        canvas.style.left = '0px';
        canvas.style.right = '0px';
        canvas.style.top = '140px';
    };

    var setGradient = function(gradientStops) {
      
        // create gradient
        gradient = ctx.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, width);
      
        // iterate through colorstops
        for (var position in gradientStops) {
            var color = gradientStops[position];
            gradient.addColorStop(position, color);
        }
    };

    var setStyles = function(hue) {
        // color stops
        var gradientStops = { 
            0: 'transparent',
            0.5: 'transparent'
        };

        // change particle color
        particleColor = timeColor;

        // create gradient
        setGradient(gradientStops);
    };

    /** 
     * Public Methods
     */
    return {

        init: function(canvasID) {
        
            canvas = document.getElementById(canvasID);
            // make sure canvas exists and that the browser understands it
            if(canvas === null || !canvas.getContext) {
                return;
            }
            // set context
            ctx = canvas.getContext("2d");
      
            // set dimensions
            setDimensions();
        
            // ui 是否要鼠标放上去点击后构成canvas的数字小圆散开的效果
            if(spreadFlag){
                this.ui();
            }
        
            for(var i = 0; i < PARTICLE_NUM; i++) {
                particles[i] = new Particle(canvas);
            }   
        
            // show FPS
            FPS.initialize(canvas, 'fps');
        
            // set defaults
            defaultStyles();
        
            // let's do this
            setInterval(loop, FRAME_RATE);
        
        },
      
         ui: function() {
        
        //     // UI: buttons and events
        //     var toggleOptions = document.getElementById('toggle-options'),
        //         options = document.getElementById('options'),
        //         onMsg = '[-] Hide Options',
        //         offMsg = '[+] Show Options',
        //         quiverBtn = document.getElementById('quivers'),
        //         gradientBtn = document.getElementById('gradient'),
        //         valentineifyBtn = document.getElementById('valentineify'),
        //         colorBtn = document.getElementById('color');
        
        //     toggleOptions.innerHTML = offMsg;
        
        //     /**
        //      * Events
        //      */
        //     toggleOptions.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         if(options.className === 'on') {
        //             options.className = '';
        //             toggleOptions.innerHTML = offMsg;
        //         } else {
        //             options.className = 'on';
        //             toggleOptions.innerHTML = onMsg;
        //         }
        //     }, false);
        
        //     quiverBtn.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         if(quiverBtn.className === 'on') {
        //             quiverBtn.className = '';
        //             quiver = false;
        //         } else {
        //             quiverBtn.className = 'on';
        //             quiver = true;
        //         }
        //     }, false);
        
        //     gradientBtn.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         if(gradientBtn.className === 'on') {
        //             gradientBtn.className = '';
        //             bgGrad = false;
        //         } else {
        //             gradientBtn.className = 'on';
        //             bgGrad = true;
        //         }
        //     }, false);
        
        //     valentineifyBtn.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         if(valentineifyBtn.className === 'on') {
        //             valentineifyBtn.className = '';
        //             valentine = false;
        //             msgTime = 0;
        //         } else {
        //             valentineifyBtn.className = 'on';
        //             msgTime = 60;
        //             valentine = true;
        //         }
        //     }, false);
        
        //     colorBtn.addEventListener('click', function(e) {
        //         e.preventDefault();
        //         if(colorBtn.className === 'on') {
        //             colorBtn.className = '';
        //             updateColor = false;
        //         } else {
        //             colorBtn.className = 'on';
        //             updateColor = true;
        //         }
        //     }, false);
        
        //     document.addEventListener('keydown', function(e) {
        //         switch(e.keyCode ) {
        //             case 16: // shift
        //                 key.shift = true;
        //                 e.preventDefault();
        //                 break;
        //             case 38: // up key
        //                 key.up = true;
        //                 e.preventDefault();
        //                 break;
        //         }
        //     }, false);
        
        //     document.addEventListener('keyup', function(e) {
        //         switch(e.keyCode ) {
        //             case 16: // shift
        //                 key.shift = false;
        //                 e.preventDefault();
        //                 break;
        //             case 38: // space
        //                 key.up = false;
        //                 e.preventDefault();
        //                 break;
        //         }
        //     }, false);

            window.addEventListener('resize', function(e){
                setDimensions();
            }, false);
        
            canvas.addEventListener('mousedown', function(e){
                press = true;
            }, false);

            document.addEventListener('mouseup', function(e){
                press = false;
            }, false);
        
            canvas.addEventListener('mousemove', function(e) {
                if(press) {
                    mouse = setCoordinates(e);
                }
            }, false);
        
        //     // @TODO: add touch events
        
         }
      
    };
    
  })();
  
  // Create new particles
  var Particle = function(canvas) {
  
        var range = Math.random() * 180 / Math.PI, // random starting point
            spread = canvas.height, // how far away from text should the particles begin?
            size = Math.random() * 7; // random size of particle
    
        this.delta = 0.25;
        this.x = 0;
        this.y = 0;
    
        // starting positions
        this.px = (canvas.width / 2) + (Math.cos(range) * spread);
        this.py = (canvas.height / 2) + (Math.sin(range) * spread);
    
        this.velocityX = Math.floor(Math.random() * 10) - 5;
        this.velocityY = Math.floor(Math.random() * 10) - 5;
    
        this.size  = size;
        this.origSize = size;
    
        this.inText = false;
    
  };
  
  var FPS = {

    // defaults
    delta: 0,
    lastTime: 0,
    frames: 0,
    totalTime: 0,
    updateTime: 0,
    updateFrames: 0,

    initialize: function(canvasID, fpsID) {
        this.lastTime = (new Date()).getTime();
        if(!document.getElementById(fpsID) && document.getElementById(canvasID)) {
            this.createFPS(canvasID, fpsID);
        }
    },

    // create FPS div if needed
    createFPS: function(canvasID, fpsID) {
        var div = document.createElement('div');
        div.setAttribute('id', fpsID);
        var canvas = document.getElementById(canvasID);
        var parent = canvas.parentNode;
        div.innerHTML = "FPS AVG: 0 CURRENT: 0";
        parent.appendChild(div);
    },

    // update FPS count
    update: function(fpsID) {    
        var now = (new Date()).getTime();
        this.delta = now - this.lastTime;
        this.lastTime = now;
        this.updateTime += this.delta;
        this.totalTime += this.delta;
        this.frames++;
        this.updateFrames++;
        //document.getElementById(fpsID).innerHTML = "FPS Average: " + Math.round(1000 * this.frames / this.  totalTime) + " Current: " + Math.round(1000 * this.updateFrames / this.updateTime);
        this.updateTime = 0; // reset time
        this.updateFrames = 0; // reset frames
    }

};