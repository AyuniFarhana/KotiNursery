<?php
// Reusable sidebar CSS
?>
<style>
  :root {
    --bg: #eef3ea;
    --surface: #ffffff;
    --surface-soft: #f7faf5;
    --text: #22332f;
    --muted: #55695a;
    --accent: #3f7d4b;
    --accent-dark: #2f5f38;
    --border: rgba(76, 118, 83, 0.18);
  }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #f8fbf6 0%, #edf5e9 100%);
    color: var(--text);
  }

  .page-shell {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 0;
    min-height: 100vh;
    align-items: stretch;
  }

  .sidebar {
    position: sticky;
    top: 0;
    align-self: stretch;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 24px;
    padding: 30px 22px;
    background: #fff;
    border-right: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 0 30px rgba(24, 66, 27, 0.04);
  }

  .sidebar-brand {
    font-size: 1.55rem;
    font-weight: 800;
    color: var(--accent-dark);
    margin-bottom: 8px;
  }

  .sidebar-links {
    display: grid;
    gap: 10px;
  }

  .sidebar a {
    display: block;
    padding: 12px 14px;
    border-radius: 10px;
    color: var(--text);
    text-decoration: none;
    font-weight: 600;
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
  }

  .sidebar a:hover,
  .sidebar a:focus,
  .sidebar a.active {
    background: rgba(63, 125, 75, 0.12);
    color: var(--accent-dark);
    transform: translateX(2px);
  }

  .sidebar-logout {
    margin-top: auto;
    padding: 12px 14px;
    border-radius: 10px;
    background: #f7f7f7;
    color: #8f1c1c;
  }

  .main-content {
    padding: 28px 34px 34px;
  }

  .page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
  }

  .page-header h1 {
    margin: 0;
    font-size: clamp(2rem, 2.4vw, 2.8rem);
  }

  .card, .form-container, .table-container, .chart-section, .stats-row {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 22px;
    box-shadow: 0 12px 28px rgba(24, 66, 27, 0.05);
  }

  .main-content a.nav-btn, .main-content .return-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    background: #f4faf4;
    color: var(--accent-dark);
    text-decoration: none;
    font-weight: 700;
    margin-bottom: 18px;
  }

  @media (max-width: 980px) {
    .page-shell {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 0;
  min-height: 100vh;
  align-items: start; /* CHANGED: Prevents the sidebar from stretching all the way down */
}

.sidebar {
  position: sticky;
  top: 28px; 
  margin: 28px 0 28px 20px; 
  display: flex;
  flex-direction: column;
  /* CHANGED: This pushes the logout button perfectly to the very bottom edge of the sidebar */
  justify-content: space-between; 
  gap: 24px;
  padding: 30px 22px;
  background: #fff;
  border: 1px solid rgba(0,0,0,0.05); 
  border-radius: 22px; 
  box-shadow: 0 12px 28px rgba(24, 66, 27, 0.05);
  
  /* CHANGED: Set a fixed height that perfectly matches the default height of your page cards */
  height: 520px; 
  box-sizing: border-box;
}
.sidebar-logout {
  /* REMOVE or keep margin-top: auto to guarantee it locks to the bottom edge */
  margin-top: auto; 
  padding: 12px 14px;
  border-radius: 10px;
  background: #f7f7f7;
  color: #8f1c1c;
  text-align: center;
}
  }
.sidebar-brand {
  font-size: 1.25rem; /* Overall font scale for the primary title */
  font-weight: 800;   /* Keeps the main title heavy */
  color: var(--accent-dark);
  margin-bottom: 20px;
  
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px; /* Reduced gap for a tighter look between lines */
  text-align: center;
}

/* Secondary line styles */
.brand-subtext {
  font-size: 0.95rem;     /* Slightly smaller text */
  font-weight: 500;       /* Normal/regular thickness (not bold) */
  color: var(--muted);    /* Uses your muted green variable for a clean contrast */
}

.sidebar-logo {
  width: 100px;         /* Balanced, medium-small size */
  height: 100px;
  object-fit: contain;
  border-radius: 4px;
}
</style>
