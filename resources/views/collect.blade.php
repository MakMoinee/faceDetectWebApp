<!DOCTYPE html>
<html>

<head>
    <title>Image Data Collection & Model Training</title>
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
            margin-bottom: 20px;
        }

        #controls {
            margin-top: 100px;
        }
    </style>
</head>

<body>
    <h2>Image Data Collection and Model Training</h2>
    <div id="controls">
        <button id="startCameraBtn">Start Camera</button>
        <input type="text" id="personName" placeholder="Enter name for face">
        <button id="captureImageBtn">Capture 30 Images</button>
        <p id="captureCount">Captured: 0 / 30</p>
        <button id="trainModelBtn">Train Model</button>
        <p id="message"></p>
        <h3>Registered Faces: <span id="registeredFaces"></span></h3>
    </div>

    <div id="container">
        <video id="video" autoplay muted></video>
        <canvas id="canvas"></canvas>
    </div>

    <script>
        const startCameraBtn = document.getElementById('startCameraBtn');
        const captureImageBtn = document.getElementById('captureImageBtn');
        const trainModelBtn = document.getElementById('trainModelBtn');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        const messageElement = document.getElementById('message');
        const personNameInput = document.getElementById('personName');
        const registeredFacesSpan = document.getElementById('registeredFaces');
        const captureCountElement = document.getElementById('captureCount');

        let stream;
        let cameraActive = false;
        const IMAGES_TO_CAPTURE = 30;
        let currentCapturedCount = 0;
        let baseURL = "{{ $baseURL }}";

        async function updateRegisteredFaces() {
            try {
                const response = await fetch(`${baseURL}/get_registered_faces`);
                const data = await response.json();
                if (data.success) {
                    const names = Object.keys(data.class_name_to_id).sort((a, b) => data.class_name_to_id[a] - data
                        .class_name_to_id[b]);
                    registeredFacesSpan.innerText = names.join(', ') || 'None';
                } else {
                    registeredFacesSpan.innerText = 'Error fetching faces.';
                    console.error('Error fetching registered faces:', data.error);
                }
            } catch (e) {
                registeredFacesSpan.innerText = 'Error fetching faces.';
                console.error('Network error fetching registered faces:', e);
            }
        }

        updateRegisteredFaces();

        startCameraBtn.onclick = async () => {
            if (cameraActive) return;

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
                cameraActive = true;
            } catch (e) {
                alert('Camera access denied or not available');
                console.error(e);
            }
        };

        // Function to capture a single image
        async function captureSingleImage(personName) {
            const offscreen = document.createElement('canvas');
            offscreen.width = video.videoWidth;
            offscreen.height = video.videoHeight;
            const offctx = offscreen.getContext('2d');
            offctx.drawImage(video, 0, 0, offscreen.width, offscreen.height);

            const dataUrl = offscreen.toDataURL('image/jpeg', 0.7);
            const base64 = dataUrl.split(',')[1];

            try {
                const detectResponse = await fetch(`${baseURL}/detect_frame`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        image: base64
                    })
                });
                const detectData = await detectResponse.json();

                // IMPORTANT: Ensure detections are present before proceeding
                if (!detectData.faces || detectData.faces.length === 0) {
                    return {
                        success: false,
                        error: "No faces (persons) detected in the frame. Please ensure your face is visible."
                    };
                }

                // Send the captured image and detections to the Flask backend
                const captureResponse = await fetch('/capture_data', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        image: base64,
                        detections: detectData.faces,
                        name: personName
                    })
                });
                const captureData = await captureResponse.json();

                if (captureData.success) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = 'blue';
                    ctx.font = '16px Arial';
                    ctx.fillStyle = 'blue';
                    // Draw the detections on the client-side for immediate visual feedback
                    detectData.faces.forEach(face => {
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
                    return {
                        success: true,
                        filename: captureData.filename,
                        detections_count: detectData.faces.length
                    };
                } else {
                    return {
                        success: false,
                        error: captureData.error
                    };
                }
            } catch (e) {
                return {
                    success: false,
                    error: `Error during capture: ${e.message}`
                };
            }
        }

        captureImageBtn.onclick = async () => {
            if (!cameraActive) {
                alert('Please start the camera first.');
                return;
            }

            const personName = personNameInput.value.trim();
            if (!personName) {
                alert('Please enter a name for the face.');
                return;
            }

            captureImageBtn.disabled = true;
            trainModelBtn.disabled = true;
            messageElement.innerText = `Starting to capture ${IMAGES_TO_CAPTURE} images for ${personName}...`;
            currentCapturedCount = 0;
            captureCountElement.innerText = `Captured: ${currentCapturedCount} / ${IMAGES_TO_CAPTURE}`;


            for (let i = 0; i < IMAGES_TO_CAPTURE; i++) {
                messageElement.innerText = `Capturing image ${i + 1} of ${IMAGES_TO_CAPTURE} for ${personName}...`;
                const result = await captureSingleImage(personName);
                if (result.success) {
                    currentCapturedCount++;
                    captureCountElement.innerText = `Captured: ${currentCapturedCount} / ${IMAGES_TO_CAPTURE}`;
                    updateRegisteredFaces();
                } else {
                    messageElement.innerText =
                        `Capture failed for image ${i + 1}: ${result.error}. Stopping capture.`;
                    break;
                }
                await new Promise(resolve => setTimeout(resolve, 500)); // 0.5 second delay between captures
            }

            if (currentCapturedCount === IMAGES_TO_CAPTURE) {
                messageElement.innerText = `Finished capturing ${IMAGES_TO_CAPTURE} images for ${personName}.`;
            } else {
                messageElement.innerText =
                    `Stopped capturing. Captured ${currentCapturedCount} / ${IMAGES_TO_CAPTURE} images.`;
            }
            captureImageBtn.disabled = false;
            trainModelBtn.disabled = false;
        };

        trainModelBtn.onclick = async () => {
            messageElement.innerText = "Training model... This may take a while.";
            trainModelBtn.disabled = true;
            captureImageBtn.disabled = true;
            try {
                const response = await fetch(`${baseURL}/train_model`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    messageElement.innerText =
                        `Training complete! Model saved to: ${data.model_path}. Reload /stream to use it.`;
                } else {
                    messageElement.innerText = `Training failed: ${data.error}`;
                }
            } catch (e) {
                messageElement.innerText = `Error during training request: ${e.message}`;
                console.error('Error during training request:', e);
            } finally {
                trainModelBtn.disabled = false;
                captureImageBtn.disabled = false;
            }
        };
    </script>
</body>

</html>
