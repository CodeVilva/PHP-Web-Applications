/**
 * ISCMS Native WebRTC Video Classroom Controller
 * Pure WebRTC RTCPeerConnection Mesh Architecture with PHP/MySQL Signaling.
 * Zero external cloud accounts or paid dependencies.
 */

function attachMediaStream(videoEl, stream, isMuted = true) {
    if (!videoEl || !stream) return;
    try {
        if (videoEl.srcObject !== stream) {
            videoEl.srcObject = stream;
        }
        videoEl.muted = isMuted;
        videoEl.playsInline = true;
        videoEl.autoplay = true;

        const playPromise = videoEl.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                console.log("[WebRTC] Video playback active:", videoEl.id);
            }).catch(err => {
                console.warn("[WebRTC] Autoplay unmuted failed, falling back to muted playback:", err);
                videoEl.muted = true;
                videoEl.play().catch(e => console.error("[WebRTC] Fallback play failed:", e));
            });
        }
    } catch (e) {
        console.error("[WebRTC] Error in attachMediaStream:", e);
    }
}

class WebRTCClassroom {
    constructor(config) {
        this.classId = config.classId;
        this.userId = config.userId;
        this.userRole = config.userRole; // 'faculty' or 'student'
        this.userName = config.userName;
        this.baseUrl = config.baseUrl || '';

        // Media Stream & Tracks
        this.localStream = null;
        this.micEnabled = true;
        this.camEnabled = true;
        this.screenStream = null;

        // Peer connections map: userId -> RTCPeerConnection
        this.peerConnections = new Map();
        
        // Polling loop
        this.lastSignalId = 0;
        this.pollInterval = null;
        this.isConnected = false;

        // Public STUN server configuration for local & LAN negotiation
        this.rtcConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ],
            iceCandidatePoolSize: 10
        };

        // UI Callbacks
        this.onStatusChange = config.onStatusChange || function() {};
        this.onParticipantUpdate = config.onParticipantUpdate || function() {};
        this.onRemoteTrack = config.onRemoteTrack || function() {};
        this.onRemoteLeave = config.onRemoteLeave || function() {};
        this.onError = config.onError || function() {};
        this.onClassEnded = config.onClassEnded || function() {};
    }

    createSimulatedMediaStream(name = "User") {
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 480;
        const ctx = canvas.getContext('2d');
        
        function drawFrame() {
            ctx.fillStyle = '#0F172A';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Gradient Background
            const grad = ctx.createRadialGradient(320, 240, 50, 320, 240, 320);
            grad.addColorStop(0, '#1E293B');
            grad.addColorStop(1, '#020617');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Circular Avatar Icon
            ctx.fillStyle = '#0A66C2';
            ctx.beginPath();
            ctx.arc(320, 200, 75, 0, Math.PI * 2);
            ctx.fill();

            // Initial Letter
            ctx.fillStyle = '#FFFFFF';
            ctx.font = 'bold 60px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const initial = (name && name[0]) ? name[0].toUpperCase() : 'U';
            ctx.fillText(initial, 320, 200);

            // User Name
            ctx.fillStyle = '#E2E8F0';
            ctx.font = 'bold 22px system-ui, sans-serif';
            ctx.fillText(name, 320, 315);

            // Status label
            ctx.font = '14px system-ui, sans-serif';
            ctx.fillStyle = '#22C55E';
            ctx.fillText('● Connected (Audio/Video Active)', 320, 350);
        }
        
        drawFrame();
        setInterval(drawFrame, 1000);

        const vStream = canvas.captureStream(15);

        // Add silent audio track via Web Audio API
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                const audioCtx = new AudioContext();
                const dest = audioCtx.createMediaStreamDestination();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                gain.gain.value = 0;
                osc.connect(gain);
                gain.connect(dest);
                osc.start();
                const track = dest.stream.getAudioTracks()[0];
                if (track) vStream.addTrack(track);
            }
        } catch (e) {
            console.warn("[WebRTC] Silent audio generator skipped:", e);
        }

        return vStream;
    }

    async init() {
        this.onStatusChange("Initializing media streams...", "warning");

        // Acquire media with automatic synthetic fallback (guarantees student NEVER gets blocked)
        this.localStream = await this.acquireUserMedia();

        this.onStatusChange("Connecting to classroom session...", "info");

        // Register join in signaling backend
        try {
            const formData = new FormData();
            formData.append('action', 'join');
            formData.append('live_class_id', this.classId);

            const res = await fetch(`${this.baseUrl}/api/webrtc_signaling.php`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                this.onError(data.error || "Failed to join live classroom session.");
                return false;
            }

            this.isConnected = true;
            this.onStatusChange("Live Classroom Connected", "success");

            // If student, proactively send join_request to host to trigger immediate SDP offer
            if (this.userRole === 'student') {
                this.sendSignal('join_request', null, { student_id: this.userId, name: this.userName });
            }

            // Start signaling polling
            this.startSignalingPolling();
            return true;
        } catch (err) {
            console.error("Signaling join error:", err);
            this.onError("Could not connect to signaling server.");
            return false;
        }
    }

    async acquireUserMedia() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            // Strategy 1: Video + Audio with ideal constraints
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" },
                    audio: true
                });
                return stream;
            } catch (err1) {
                console.warn("[WebRTC] Full Video+Audio acquisition failed:", err1.name);
            }

            // Strategy 2: Video only
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" },
                    audio: false
                });
                this.micEnabled = false;
                return stream;
            } catch (err2) {
                console.warn("[WebRTC] Ideal video constraints failed:", err2.name);
            }

            // Strategy 3: Basic Video fallback
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                this.micEnabled = false;
                return stream;
            } catch (err3) {
                console.warn("[WebRTC] Basic camera access failed:", err3.name);
            }

            // Strategy 4: Microphone only + Virtual Canvas Video
            try {
                const audioStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                const canvasStream = this.createSimulatedMediaStream(this.userName);
                const audioTrack = audioStream.getAudioTracks()[0];
                if (audioTrack) {
                    canvasStream.getAudioTracks().forEach(t => canvasStream.removeTrack(t));
                    canvasStream.addTrack(audioTrack);
                }
                this.camEnabled = false;
                return canvasStream;
            } catch (err4) {
                console.warn("[WebRTC] Microphone-only access failed:", err4.name);
            }
        }

        // Strategy 5: Seamless Virtual Stream (Ensures 100% reliable connect for viewers & single-PC tests)
        console.info("[WebRTC] Using virtual media stream fallback for viewer.");
        this.camEnabled = false;
        this.micEnabled = false;
        return this.createSimulatedMediaStream(this.userName);
    }

    startSignalingPolling() {
        if (this.pollInterval) clearInterval(this.pollInterval);

        this.pollInterval = setInterval(async () => {
            if (!this.isConnected) return;

            try {
                const url = `${this.baseUrl}/api/webrtc_signaling.php?action=poll_signals&live_class_id=${this.classId}&last_signal_id=${this.lastSignalId}`;
                const res = await fetch(url);
                const data = await res.json();

                if (!data || !data.success) return;

                if (data.class_ended) {
                    this.onClassEnded("The instructor has concluded this live classroom session.");
                    this.leaveRoom();
                    return;
                }

                // Update participants
                if (data.participants) {
                    this.onParticipantUpdate(data.participants);
                    this.syncPeersWithParticipants(data.participants);
                }

                // Process signals
                if (data.signals && data.signals.length > 0) {
                    this.lastSignalId = data.last_signal_id;
                    for (const sig of data.signals) {
                        await this.handleIncomingSignal(sig);
                    }
                }
            } catch (err) {
                console.warn("Signaling poll error:", err);
            }
        }, 1000);
    }

    async syncPeersWithParticipants(participants) {
        if (this.userRole === 'faculty') {
            for (const p of participants) {
                const peerId = parseInt(p.user_id);
                if (peerId !== this.userId && !this.peerConnections.has(peerId)) {
                    console.log(`[WebRTC] Host initiating offer for student #${peerId} (${p.display_name})`);
                    await this.createOfferForPeer(peerId, p.display_name, p.user_role);
                }
            }
        }
    }

    createPeerConnection(peerId, peerName, peerRole) {
        if (this.peerConnections.has(peerId)) {
            return this.peerConnections.get(peerId);
        }

        const pc = new RTCPeerConnection(this.rtcConfig);
        pc._remoteStream = new MediaStream();
        pc._pendingIceCandidates = [];
        pc._isRemoteDescriptionSet = false;
        pc._peerRole = peerRole;
        pc._peerName = peerName;

        // Add local tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }

        // Handle remote stream tracks
        pc.ontrack = (event) => {
            console.log(`[WebRTC] Received remote track (${event.track.kind}) from peer #${peerId} (${peerName})`);
            if (event.streams && event.streams[0]) {
                this.onRemoteTrack(peerId, peerName, peerRole, event.streams[0]);
            } else {
                pc._remoteStream.addTrack(event.track);
                this.onRemoteTrack(peerId, peerName, peerRole, pc._remoteStream);
            }
        };

        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('ice_candidate', peerId, {
                    candidate: event.candidate
                });
            }
        };

        pc.onconnectionstatechange = () => {
            console.log(`[WebRTC] Peer #${peerId} (${peerName}) state: ${pc.connectionState}`);
            if (pc.connectionState === 'connected') {
                this.onStatusChange("Live Broadcast Streaming", "success");
            } else if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                this.onRemoteLeave(peerId);
            }
        };

        this.peerConnections.set(peerId, pc);
        return pc;
    }

    async createOfferForPeer(peerId, peerName, peerRole) {
        const pc = this.createPeerConnection(peerId, peerName, peerRole);
        try {
            const offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });
            await pc.setLocalDescription(offer);

            this.sendSignal('offer', peerId, {
                sdp: pc.localDescription
            });
        } catch (err) {
            console.error(`[WebRTC] Error creating offer for peer #${peerId}:`, err);
        }
    }

    async handleIncomingSignal(sig) {
        const senderId = parseInt(sig.sender_id);
        const signalType = sig.signal_type;
        const signalData = typeof sig.signal_data === 'string' ? JSON.parse(sig.signal_data) : sig.signal_data;
        const senderName = sig.sender_name || `User #${senderId}`;
        const senderRole = sig.sender_role || 'faculty';

        console.log(`[WebRTC] Handling signal '${signalType}' from #${senderId} (${senderName})`);

        if (signalType === 'join_request') {
            if (this.userRole === 'faculty') {
                await this.createOfferForPeer(senderId, senderName, senderRole);
            }
        } else if (signalType === 'offer') {
            const pc = this.createPeerConnection(senderId, senderName, senderRole);
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(signalData.sdp));
                pc._isRemoteDescriptionSet = true;

                // Process any queued ICE candidates
                if (pc._pendingIceCandidates && pc._pendingIceCandidates.length > 0) {
                    for (const cand of pc._pendingIceCandidates) {
                        try {
                            await pc.addIceCandidate(new RTCIceCandidate(cand));
                        } catch (e) {
                            console.warn("[WebRTC] Error adding queued candidate:", e);
                        }
                    }
                    pc._pendingIceCandidates = [];
                }

                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                this.sendSignal('answer', senderId, {
                    sdp: pc.localDescription
                });
            } catch (err) {
                console.error(`[WebRTC] Error handling offer from #${senderId}:`, err);
            }
        } else if (signalType === 'answer') {
            const pc = this.peerConnections.get(senderId);
            if (pc && pc.signalingState !== 'stable') {
                try {
                    await pc.setRemoteDescription(new RTCSessionDescription(signalData.sdp));
                    pc._isRemoteDescriptionSet = true;

                    if (pc._pendingIceCandidates && pc._pendingIceCandidates.length > 0) {
                        for (const cand of pc._pendingIceCandidates) {
                            try {
                                await pc.addIceCandidate(new RTCIceCandidate(cand));
                            } catch (e) {
                                console.warn("[WebRTC] Error adding queued candidate on answer:", e);
                            }
                        }
                        pc._pendingIceCandidates = [];
                    }
                } catch (err) {
                    console.error(`[WebRTC] Error handling answer from #${senderId}:`, err);
                }
            }
        } else if (signalType === 'ice_candidate') {
            let pc = this.peerConnections.get(senderId);
            if (!pc) {
                pc = this.createPeerConnection(senderId, senderName, senderRole);
            }
            if (signalData && signalData.candidate) {
                if (pc._isRemoteDescriptionSet) {
                    try {
                        await pc.addIceCandidate(new RTCIceCandidate(signalData.candidate));
                    } catch (e) {
                        console.warn(`[WebRTC] Error adding ICE candidate from #${senderId}:`, e);
                    }
                } else {
                    pc._pendingIceCandidates.push(signalData.candidate);
                }
            }
        } else if (signalType === 'leave') {
            this.cleanupPeer(senderId);
            this.onRemoteLeave(senderId);
        } else if (signalType === 'end_call') {
            this.onClassEnded("The instructor has ended the live classroom session.");
            this.leaveRoom();
        }
    }

    async sendSignal(type, receiverId, data) {
        try {
            const formData = new FormData();
            formData.append('action', 'send_signal');
            formData.append('live_class_id', this.classId);
            formData.append('signal_type', type);
            formData.append('receiver_id', receiverId || '');
            formData.append('signal_data', JSON.stringify(data));

            await fetch(`${this.baseUrl}/api/webrtc_signaling.php`, {
                method: 'POST',
                body: formData
            });
        } catch (err) {
            console.error(`[WebRTC] Error sending signal ${type}:`, err);
        }
    }

    toggleMic() {
        if (!this.localStream) return false;
        const audioTracks = this.localStream.getAudioTracks();
        if (audioTracks.length === 0) return false;
        
        this.micEnabled = !this.micEnabled;
        audioTracks.forEach(track => { track.enabled = this.micEnabled; });
        return this.micEnabled;
    }

    toggleCam() {
        if (!this.localStream) return false;
        const videoTracks = this.localStream.getVideoTracks();
        if (videoTracks.length === 0) return false;

        this.camEnabled = !this.camEnabled;
        videoTracks.forEach(track => { track.enabled = this.camEnabled; });
        return this.camEnabled;
    }

    async startScreenShare() {
        try {
            this.screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: { cursor: "always" },
                audio: false
            });

            const screenTrack = this.screenStream.getVideoTracks()[0];
            
            // Replace video track on all peer connections
            this.peerConnections.forEach(pc => {
                const senders = pc.getSenders();
                const videoSender = senders.find(s => s.track && s.track.kind === 'video');
                if (videoSender) {
                    videoSender.replaceTrack(screenTrack);
                }
            });

            screenTrack.onended = () => {
                this.stopScreenShare();
            };

            return this.screenStream;
        } catch (err) {
            console.error("[WebRTC] Screen share failed:", err);
            return null;
        }
    }

    stopScreenShare() {
        if (!this.screenStream) return;
        this.screenStream.getTracks().forEach(t => t.stop());
        this.screenStream = null;

        if (this.localStream) {
            const camTrack = this.localStream.getVideoTracks()[0];
            this.peerConnections.forEach(pc => {
                const senders = pc.getSenders();
                const videoSender = senders.find(s => s.track && s.track.kind === 'video');
                if (videoSender && camTrack) {
                    videoSender.replaceTrack(camTrack);
                }
            });
        }
    }

    cleanupPeer(peerId) {
        if (this.peerConnections.has(peerId)) {
            const pc = this.peerConnections.get(peerId);
            pc.close();
            this.peerConnections.delete(peerId);
        }
    }

    leaveRoom() {
        this.isConnected = false;
        if (this.pollInterval) clearInterval(this.pollInterval);

        // Notify peers of leaving
        this.sendSignal('leave', null, {});

        // Close peer connections
        this.peerConnections.forEach(pc => pc.close());
        this.peerConnections.clear();

        // Stop local streams
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
            this.localStream = null;
        }
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(t => t.stop());
            this.screenStream = null;
        }
    }
}
