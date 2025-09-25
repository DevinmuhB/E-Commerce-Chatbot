// AI Chat JavaScript
class AIChat {
    constructor() {
        this.chatButton = document.getElementById('chatButton');
        this.chatBox = document.getElementById('chatBox');
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.sendButton = document.getElementById('sendButton');
        this.namaUser = window.namaUser || 'User';
        this.isLoggedIn = window.isLoggedIn || false;
        this.hasWelcomed = false;
        
        this.init();
    }
    
    init() {
        this.chatButton.onclick = () => this.toggleChat();
        this.sendButton.onclick = () => this.sendMessage();
        this.chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
        
        // Tambahkan event listener untuk tombol close
        const closeButton = document.querySelector('.close-button');
        if (closeButton) {
            closeButton.addEventListener('click', () => this.toggleChat());
        }
    }
    
    toggleChat() {
        const isCurrentlyOpen = this.chatBox.style.display === 'flex';
        
        if (!isCurrentlyOpen && !this.hasWelcomed) {
            // Chatbox akan dibuka untuk pertama kali
            this.chatBox.style.display = 'flex';
            this.showWelcomeMessage();
            this.hasWelcomed = true;
        } else {
            // Toggle normal
            this.chatBox.style.display = isCurrentlyOpen ? 'none' : 'flex';
        }
    }
    
    showWelcomeMessage() {
        // Delay sedikit agar chatbox sudah terbuka
        setTimeout(() => {
            if (this.isLoggedIn) {
                this.addMessage(this.getLoggedInWelcomeMessage(), 'ai');
            } else {
                this.addMessage(this.getGuestWelcomeMessage(), 'ai');
            }
        }, 300);
    }
    
    getLoggedInWelcomeMessage() {
        const currentTime = new Date().getHours();
        let greeting = '';
        
        if (currentTime < 12) {
            greeting = 'Selamat pagi';
        } else if (currentTime < 15) {
            greeting = 'Selamat siang';
        } else if (currentTime < 18) {
            greeting = 'Selamat sore';
        } else {
            greeting = 'Selamat malam';
        }
        
        return `
            <div style="margin-bottom: 15px;">
                <h3 style="color: #ffffff; margin-bottom: 10px; font-size: 16px; font-weight: 600;">👋 ${greeting}, ${this.namaUser}!</h3>
                <p style="color: #e2e8f0; margin-bottom: 15px; font-size: 14px; line-height: 1.5;">
                    Selamat datang di TechAI! Saya siap membantu Anda menemukan produk terbaik yang sesuai dengan kebutuhan Anda.
                </p>
            </div>
            
            <div style="background: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 15px; margin-bottom: 15px;">
                <h4 style="color: #ffffff; margin-bottom: 10px; font-size: 14px; font-weight: 600;">🎯 Apa yang bisa saya bantu?</h4>
                <ul style="color: #e2e8f0; font-size: 13px; line-height: 1.6; margin: 0; padding-left: 20px;">
                    <li>Rekomendasi produk terbaik</li>
                    <li>Informasi detail produk</li>
                    <li>Status keranjang belanja</li>
                    <li>Informasi pembayaran</li>
                    <li>Lokasi toko</li>
                </ul>
            </div>
            
            <div style="background: linear-gradient(45deg, #007bff, #0056b3); border-radius: 10px; padding: 12px; text-align: center;">
                <p style="color: #ffffff; margin: 0; font-size: 13px; font-weight: 600;">
                    💡 Tips: Coba tanyakan "ada rekomendasi produk ga?" untuk melihat produk terbaik kami!
                </p>
            </div>
        `;
    }
    
    getGuestWelcomeMessage() {
        const currentTime = new Date().getHours();
        let greeting = '';
        
        if (currentTime < 12) {
            greeting = 'Selamat pagi';
        } else if (currentTime < 15) {
            greeting = 'Selamat siang';
        } else if (currentTime < 18) {
            greeting = 'Selamat sore';
        } else {
            greeting = 'Selamat malam';
        }
        
        return `
            <div style="margin-bottom: 15px;">
                <h3 style="color: #ffffff; margin-bottom: 10px; font-size: 16px; font-weight: 600;">👋 ${greeting}!</h3>
                <p style="color: #e2e8f0; margin-bottom: 15px; font-size: 14px; line-height: 1.5;">
                    Selamat datang di TechAI! Saya siap membantu Anda menemukan produk terbaik yang sesuai dengan kebutuhan Anda.
                </p>
            </div>
            
            <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 12px; padding: 15px; margin-bottom: 15px;">
                <h4 style="color: #ffc107; margin-bottom: 10px; font-size: 14px; font-weight: 600;">🔐 Login untuk Pengalaman Lebih Baik</h4>
                <p style="color: #e2e8f0; margin-bottom: 12px; font-size: 13px; line-height: 1.5;">
                    Untuk mendapatkan pengalaman terbaik, silakan login atau buat akun terlebih dahulu.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="login/" style="display: inline-block; padding: 8px 16px; background: linear-gradient(45deg, #28a745, #20c997); color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        🔑 Login
                    </a>
                    <a href="login/" style="display: inline-block; padding: 8px 16px; background: linear-gradient(45deg, #007bff, #0056b3); color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        ✨ Daftar
                    </a>
                </div>
            </div>
            
            <div style="background: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 15px; margin-bottom: 15px;">
                <h4 style="color: #ffffff; margin-bottom: 10px; font-size: 14px; font-weight: 600;">🎯 Apa yang bisa saya bantu?</h4>
                <ul style="color: #e2e8f0; font-size: 13px; line-height: 1.6; margin: 0; padding-left: 20px;">
                    <li>Rekomendasi produk terbaik</li>
                    <li>Informasi detail produk</li>
                    <li>Informasi pembayaran</li>
                    <li>Lokasi toko</li>
                </ul>
            </div>
            
            <div style="background: linear-gradient(45deg, #007bff, #0056b3); border-radius: 10px; padding: 12px; text-align: center;">
                <p style="color: #ffffff; margin: 0; font-size: 13px; font-weight: 600;">
                    💡 Tips: Coba tanyakan "ada rekomendasi produk ga?" untuk melihat produk terbaik kami!
                </p>
            </div>
        `;
    }
    
    addMessage(text, sender) {
        const msg = document.createElement('div');
        msg.className = 'message ' + sender;
        
        // Avatar
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        if(sender === 'ai') {
            avatar.innerHTML = '<i class="fa-solid fa-headset"></i>';
        } else {
            avatar.innerHTML = '<i class="fa fa-user"></i>';
        }
        
        // Bubble
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.innerHTML = text;
        
        msg.appendChild(avatar);
        msg.appendChild(bubble);
        this.chatMessages.appendChild(msg);
        this.scrollToBottom();
    }
    
    showTyping() {
        const typing = document.createElement('div');
        typing.className = 'typing ai';
        typing.id = 'typing-indicator';
        typing.innerHTML = 'TechAI sedang mengetik...';
        this.chatMessages.appendChild(typing);
        this.scrollToBottom();
    }
    
    hideTyping() {
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
    }
    
    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
    
    sendMessage() {
        const text = this.chatInput.value.trim();
        if (!text) return;
        
        this.addMessage(text, 'user');
        this.chatInput.value = '';
        this.showTyping();
        
        fetch('gemini_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(res => res.json())
        .then(data => {
            this.hideTyping();
        
            // Tambahkan ke dalam bubble chat
            let responseWithMeta = `
                ${data.response}
                <div class="meta-info">
                    <small>Akurasi: ${data.accuracy}% | Response time: ${data.response_time}s</small>
                </div>
            `;
        
            this.addMessage(responseWithMeta || 'Maaf, terjadi gangguan sistem.', 'ai');
        })        
        .catch(() => {
            this.hideTyping();
            this.addMessage('Maaf, terjadi gangguan sistem.', 'ai');
        });
    }
}

// Global instance untuk akses dari HTML
let aiChatInstance;

// Initialize AI Chat when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    aiChatInstance = new AIChat();
});

// Fungsi global untuk akses dari HTML onclick
function toggleChat() {
    if (aiChatInstance) {
        aiChatInstance.toggleChat();
    }
} 