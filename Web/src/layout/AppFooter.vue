<script setup></script>

<template>
    <div class="layout-footer">
        <div class="footer-brand">
            <i class="pi pi-graduation-cap footer-icon"></i>
            <span class="footer-name">SchoolHub</span>
            <span class="footer-year">2026</span>
        </div>
        <span class="footer-divider">·</span>
        <span class="footer-copy">All rights reserved</span>
    </div>
</template>

<style scoped>
.layout-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.footer-brand {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.footer-icon {
    font-size: 1rem;
    background: linear-gradient(135deg, #34d399, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-name {
    background: linear-gradient(135deg, #34d399, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-year {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #10b981, #047857);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    line-height: 1.4;
}

.footer-divider {
    color: var(--surface-400, #94a3b8);
    font-size: 1rem;
    line-height: 1;
}

.footer-copy {
    font-size: 0.8rem;
    color: var(--surface-500, #64748b);
    letter-spacing: 0.01em;
}
</style>
