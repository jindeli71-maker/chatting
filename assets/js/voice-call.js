/**
 * WebRTC voice calls with PHP signaling.
 */
(function (global) {
	class VoiceCall {
		constructor(options = {}) {
			this.options = Object.assign({
				userId: null,
				apiUrl: 'voice_call_api.php',
				iceServers: [
					{ urls: 'stun:stun.l.google.com:19302' },
					{ urls: 'stun:stun1.l.google.com:19302' }
				],
				onOutgoing: function () {},
				onIncoming: function () {},
				onConnected: function () {},
				onEnded: function () {},
				onStatus: function () {},
				onError: function () {}
			}, options);

			this.localStream = null;
			this.peerConnection = null;
			this.callId = null;
			this.peerId = null;
			this.peerName = '';
			this.isCaller = false;
			this.isCallActive = false;
			this.lastSignalId = 0;
			this.signalTimer = null;
			this.incomingTimer = null;
			this.statusTimer = null;
			this.handledIncomingId = null;
			this.muted = false;
			this._ending = false;

			this.remoteAudio = document.getElementById('remoteAudio');
			if (!this.remoteAudio) {
				this.remoteAudio = document.createElement('audio');
				this.remoteAudio.id = 'remoteAudio';
				this.remoteAudio.autoplay = true;
				this.remoteAudio.setAttribute('playsinline', 'true');
				document.body.appendChild(this.remoteAudio);
			}

			this.startIncomingPoll();
			this.heartbeat();
			this._hb = setInterval(() => this.heartbeat(), 12000);
			console.log('[VoiceCall] ready', { userId: this.options.userId });
		}

		async api(action, data) {
			data = data || {};
			const body = new URLSearchParams(Object.assign({ action: action }, data));
			const res = await fetch(this.options.apiUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			});
			const text = await res.text();
			let json;
			try {
				json = JSON.parse(text);
			} catch (e) {
				throw new Error('API error (not JSON). Are you logged in?');
			}
			if (!json.success) {
				throw new Error(json.error || 'Call failed');
			}
			return json;
		}

		async heartbeat() {
			try { await this.api('heartbeat'); } catch (e) {}
		}

		startIncomingPoll() {
			if (this.incomingTimer) return;
			this.incomingTimer = setInterval(() => this.pollIncoming(), 1500);
			this.pollIncoming();
		}

		async pollIncoming() {
			if (this.isCallActive) return;
			try {
				const data = await this.api('check_incoming_calls');
				const call = data.incoming_call;
				if (call && call.id && String(call.id) !== String(this.handledIncomingId)) {
					this.handledIncomingId = call.id;
					console.log('[VoiceCall] incoming', call);
					this.options.onIncoming(call);
				}
			} catch (e) {
				console.warn('[VoiceCall] incoming poll', e.message);
			}
		}

		async startCall(peerId, peerName) {
			peerName = peerName || 'Friend';
			peerId = Number(peerId) || 0;
			console.log('[VoiceCall] startCall', peerId, peerName);

			if (this.isCallActive) {
				this.options.onError('Call already in progress');
				return false;
			}
			if (!peerId) {
				this.options.onError('No contact selected');
				return false;
			}

			// Show UI immediately
			this.peerId = peerId;
			this.peerName = peerName;
			this.isCaller = true;
			this.isCallActive = true;
			this._ending = false;
			this.options.onOutgoing({ peer_name: peerName, peer_id: peerId });
			this.options.onStatus('Requesting microphone...');

			try {
				await this.ensureLocalMedia();
				this.options.onStatus('Starting call...');

				const result = await this.api('initiate_call', {
					receiver_id: String(peerId),
					call_type: 'voice'
				});
				console.log('[VoiceCall] initiated', result);

				this.callId = result.call_id;
				this.lastSignalId = 0;
				this.options.onStatus('Calling... (waiting for answer)');

				await this.createPeerConnection();
				const offer = await this.peerConnection.createOffer({ offerToReceiveAudio: true });
				await this.peerConnection.setLocalDescription(offer);
				await this.sendSignal('offer', offer);

				this.startSignalPoll();
				this.startStatusPoll();
				return true;
			} catch (e) {
				console.error('[VoiceCall] start failed', e);
				this.options.onError(e.message || 'Failed to start call');
				await this.cleanup();
				return false;
			}
		}

		async acceptIncoming(call) {
			if (this.isCallActive) return false;
			console.log('[VoiceCall] accept', call);
			this._ending = false;
			this.isCallActive = true;
			this.isCaller = false;
			this.callId = call.id;
			this.peerId = call.caller_id;
			this.peerName = call.caller_name || 'Caller';
			this.lastSignalId = 0;
			this.options.onStatus('Connecting microphone...');

			try {
				await this.ensureLocalMedia();
				await this.api('answer_call', {
					call_id: String(call.id),
					call_action: 'accept'
				});
				await this.createPeerConnection();
				this.startSignalPoll();
				this.startStatusPoll();
				this.options.onStatus('Connecting...');
				return true;
			} catch (e) {
				console.error('[VoiceCall] accept failed', e);
				this.options.onError(e.message || 'Failed to accept');
				await this.cleanup();
				return false;
			}
		}

		async declineIncoming(callId) {
			try {
				await this.api('answer_call', {
					call_id: String(callId),
					call_action: 'decline'
				});
			} catch (e) {}
			this.handledIncomingId = callId;
		}

		async endCall() {
			if (this._ending) return;
			this._ending = true;
			const id = this.callId;
			let duration = 0;
			try {
				if (id) {
					const res = await this.api('end_call', { call_id: String(id) });
					duration = res.duration || 0;
				}
			} catch (e) {}
			await this.cleanup();
			this.options.onEnded({ duration: duration });
		}

		async ensureLocalMedia() {
			if (this.localStream) return this.localStream;
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
				throw new Error('Microphone not supported. Use Chrome/Edge on localhost.');
			}
			this.localStream = await navigator.mediaDevices.getUserMedia({
				audio: {
					echoCancellation: true,
					noiseSuppression: true
				},
				video: false
			});
			return this.localStream;
		}

		async createPeerConnection() {
			this.peerConnection = new RTCPeerConnection({ iceServers: this.options.iceServers });
			this.localStream.getTracks().forEach((track) => {
				this.peerConnection.addTrack(track, this.localStream);
			});

			this.peerConnection.ontrack = (event) => {
				const stream = event.streams[0] || new MediaStream([event.track]);
				this.remoteAudio.srcObject = stream;
				this.remoteAudio.play().catch(function () {});
				this.options.onConnected();
				this.options.onStatus('Connected');
			};

			this.peerConnection.onicecandidate = (event) => {
				if (event.candidate) {
					this.sendSignal('ice', event.candidate).catch(function () {});
				}
			};

			this.peerConnection.onconnectionstatechange = () => {
				const state = this.peerConnection && this.peerConnection.connectionState;
				console.log('[VoiceCall] pc state', state);
				if (state === 'connected') {
					this.options.onConnected();
					this.options.onStatus('Connected');
				} else if (state === 'failed') {
					this.options.onError('Connection failed');
					this.endCall();
				}
				// ignore brief "disconnected"
			};
		}

		async sendSignal(type, payload) {
			if (!this.callId) return;
			await this.api('send_signal', {
				call_id: String(this.callId),
				signal_type: type,
				payload: JSON.stringify(payload)
			});
		}

		startSignalPoll() {
			this.stopSignalPoll();
			this.signalTimer = setInterval(() => this.pollSignals(), 700);
			this.pollSignals();
		}

		stopSignalPoll() {
			if (this.signalTimer) {
				clearInterval(this.signalTimer);
				this.signalTimer = null;
			}
		}

		startStatusPoll() {
			this.stopStatusPoll();
			this.statusTimer = setInterval(() => this.pollStatus(), 1200);
		}

		stopStatusPoll() {
			if (this.statusTimer) {
				clearInterval(this.statusTimer);
				this.statusTimer = null;
			}
		}

		async pollStatus() {
			if (!this.callId || this._ending) return;
			try {
				const data = await this.api('get_call_status', { call_id: String(this.callId) });
				const st = data.call_status;
				if (!st) return;
				if (st.status === 'declined') {
					this.options.onError('Call declined');
					await this.cleanup();
					this.options.onEnded({ duration: 0 });
				} else if (st.status === 'ended') {
					await this.cleanup();
					this.options.onEnded({ duration: st.duration || 0 });
				} else if (st.status === 'connected') {
					this.options.onStatus('Connected');
				}
			} catch (e) {}
		}

		async pollSignals() {
			if (!this.callId || !this.peerConnection || this._ending) return;
			try {
				const data = await this.api('get_signals', {
					call_id: String(this.callId),
					after_id: String(this.lastSignalId)
				});
				for (let i = 0; i < (data.signals || []).length; i++) {
					const sig = data.signals[i];
					this.lastSignalId = Math.max(this.lastSignalId, Number(sig.id));
					const payload = JSON.parse(sig.payload);
					if (sig.signal_type === 'offer' && !this.isCaller) {
						await this.peerConnection.setRemoteDescription(payload);
						const answer = await this.peerConnection.createAnswer();
						await this.peerConnection.setLocalDescription(answer);
						await this.sendSignal('answer', answer);
					} else if (sig.signal_type === 'answer' && this.isCaller) {
						if (!this.peerConnection.currentRemoteDescription) {
							await this.peerConnection.setRemoteDescription(payload);
						}
					} else if (sig.signal_type === 'ice') {
						try {
							await this.peerConnection.addIceCandidate(payload);
						} catch (e) {}
					}
				}
			} catch (e) {}
		}

		toggleMute() {
			this.muted = !this.muted;
			if (this.localStream) {
				this.localStream.getAudioTracks().forEach((t) => {
					t.enabled = !this.muted;
				});
			}
			return this.muted;
		}

		async cleanup() {
			this.isCallActive = false;
			this.stopSignalPoll();
			this.stopStatusPoll();
			if (this.peerConnection) {
				try { this.peerConnection.close(); } catch (e) {}
				this.peerConnection = null;
			}
			if (this.localStream) {
				this.localStream.getTracks().forEach((t) => t.stop());
				this.localStream = null;
			}
			if (this.remoteAudio) this.remoteAudio.srcObject = null;
			this.callId = null;
			this.peerId = null;
			this.isCaller = false;
			this.lastSignalId = 0;
			this.muted = false;
		}
	}

	global.VoiceCall = VoiceCall;
	console.log('[VoiceCall] class loaded');
})(window);
