<!DOCTYPE html>
<html>

<head>
    <title>Live Face Detection Stream</title>
    <style>
        video,
        canvas {
            position: absolute;
            left: 0;
            top: 0;
            width: 640px;
            height: 480px;
        }

        #container {
            position: relative;
            width: 640px;
            height: 480px;
        }
    </style>
</head>

<body>
    <h2>Click Start to open camera and start face detection</h2>
    <p>To detect a specific person, append `?person=NAME` to the URL (e.g., `/stream?person=JohnDoe`).</p>
    <button id="startBtn">Start Detect</button>
    <br>

    <div id="container">
        <video id="video" autoplay muted></video>
        <canvas id="canvas"></canvas>
    </div>

    <script>
        const startBtn = document.getElementById('startBtn');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');

        let stream;
        let detecting = false;
        let baseURL = "{{ $baseURL }}";

        startBtn.onclick = async () => {
            if (detecting) return;

            try {
                if (navigator.permissions) {
                    navigator.permissions.query({
                            name: 'camera'
                        })
                        .then((permissionObj) => {
                            if (permissionObj.state === 'denied') {
                                alert('Camera access has been denied. Please enable it in browser settings.');
                                return;
                            }
                        })
                        .catch((error) => {
                            console.log('Got error :', error);
                        });
                }

                stream = await navigator.mediaDevices.getUserMedia({
                    video: true
                });
                video.srcObject = stream;
                detecting = true;
                detectFrame();
            } catch (e) {
                alert('Camera access denied or not available');
                console.error(e);
            }
        };

        async function detectFrame() {
            if (!detecting) return;

            const offscreen = document.createElement('canvas');
            offscreen.width = video.videoWidth;
            offscreen.height = video.videoHeight;
            const offctx = offscreen.getContext('2d');
            offctx.drawImage(video, 0, 0, offscreen.width, offscreen.height);

            const dataUrl = offscreen.toDataURL('image/jpeg', 0.7);
            const base64 = dataUrl.split(',')[1];

            // Get person name from URL query parameter
            const urlParams = new URLSearchParams(window.location.search);
            const personToDetect = urlParams.get('person');

            try {
                // Pass personToDetect to the backend
                const response = await fetch(`${baseURL}/detect_frame` + (personToDetect ?
                    `?person_name=${personToDetect}` :
                    ''), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        image: base64
                    })
                });
                const data = await response.json();

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                ctx.lineWidth = 2;
                ctx.strokeStyle = 'red';
                ctx.font = '16px Arial';
                ctx.fillStyle = 'red';

                data.faces.forEach(face => {
                    const x = face.xmin;
                    const y = face.ymin;
                    const w = face.xmax - face.xmin;
                    const h = face.ymax - face.ymin;
                    ctx.strokeRect(x, y, w, h);
                    ctx.fillText(
                        `${face.name} (${(face.confidence*100).toFixed(1)}%)`,
                        x,
                        y > 20 ? y - 5 : y + 15
                    );
                });

            } catch (e) {
                console.error('Error detecting frame:', e);
            }

            setTimeout(detectFrame, 200);
        }
    </script>
</body>

</html>
