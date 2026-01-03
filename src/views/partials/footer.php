</main>
<footer class="ca-footer">
    <div class="footer-card">
        <div class="footer-brand">
            <img src="/assets/images/logo.png" alt="CA Service Hub logo">
            <div>
                <p class="footer-title">CA Service Hub</p>
                <p class="footer-subtitle">Chartered advisory cockpit for ledgers, tax, and audit.</p>
            </div>
        </div>
        <div class="footer-meta">
            <span>&copy; <?= date('Y') ?> <a href="https://www.extendlogics.com" target="_blank" rel="noopener">Extendlogics</a></span>
            <span>Encrypted workpapers · Role-based access · Maker-checker ready</span>
        </div>
    </div>
</footer>
<style>
.ca-footer {
    margin: 0;
    background: linear-gradient(135deg, #0f172a, #19263b 65%, #223149);
    color: #e2e8f0;
    padding: 2rem clamp(1.5rem, 4vw, 3.5rem);
}

.footer-card {
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 26px;
    padding: clamp(1.5rem, 4vw, 2.7rem);
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.12);
    box-shadow: 0 25px 55px rgba(0, 0, 0, 0.4);
}

.footer-brand {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
}

.footer-brand img {
    height: 48px;
}

.footer-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.footer-subtitle {
    margin: 0.2rem 0 0;
    color: rgba(226, 232, 240, 0.75);
}

.footer-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: rgba(226, 232, 240, 0.75);
}

.footer-meta a {
    color: inherit;
    text-decoration: underline;
}

@media (max-width: 640px) {
    .footer-brand { flex-direction: column; align-items: flex-start; }
    .footer-meta { flex-direction: column; }
}
</style>
</body>
</html>
