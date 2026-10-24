$(function () {
    let stream;
    const stop = () => { stream?.getTracks().forEach(track => track.stop()); stream = null; $('#capturePhoto').prop('disabled', true); };
    $('#startCamera').on('click', async function () {
        stop(); $('#cameraMessage').text('');
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false});
            const video = document.getElementById('receiverVideo'); video.srcObject = stream;
            $('#receiverVideo').removeClass('d-none'); $('#receiverPreview').addClass('d-none');
            video.onloadedmetadata = () => $('#capturePhoto').prop('disabled', false);
        } catch { $('#cameraMessage').text('Camera access is unavailable. You can still upload the receiver’s ID scan.'); }
    });
    $('#capturePhoto').on('click', function () {
        const video = document.getElementById('receiverVideo'), canvas = document.getElementById('receiverCanvas');
        if (!video.videoWidth) return;
        canvas.width = video.videoWidth; canvas.height = video.videoHeight; canvas.getContext('2d').drawImage(video, 0, 0);
        const data = canvas.toDataURL('image/jpeg', 0.85); $('#receiverPhotoData').val(data); $('#receiverPreview').attr('src', data).removeClass('d-none'); $('#receiverVideo').addClass('d-none'); stop();
    });
    document.getElementById('remittancePaymentPanel')?.addEventListener('wes:inline-form:hide', stop);
    window.addEventListener('pagehide', stop);
});
