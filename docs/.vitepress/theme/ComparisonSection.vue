<template>
   <section class="comparison-section">
      <div class="comparison-container">
         <div class="feature-comparison">
            <h4><span class="badge">SUPERIOR</span> Fluent API</h4>
            <p>Stop configuring backups in massive arrays. Define complex backup workflows with a clean, chainable, and expressive API that is a delight to read and write. Your backup logic now lives in version control as clean, understandable code.</p>
         </div>

         <div class="feature-comparison">
            <h4><span class="badge">BETTER</span> Automation &amp; Reliability</h4>
            <p>Built to be automated. The package encourages creating dedicated, reusable Artisan commands for your specific needs. Combined with native database tools and remote storage drivers, it provides a rock-solid foundation for your backup strategy.</p>
         </div>

         <div class="feature-comparison">
            <h4><span class="badge">SIMPLER</span> Complex Workflows</h4>
            <p>Backing up databases, files, and directories together? Adding encryption and notifications? Setting up automatic cleanup policies? What used to be complex is now simple. Chain a few methods, and you're done. No magic, just a clean API.</p>
         </div>
      </div>
   </section>
</template>

<style scoped>
.comparison-section {
   margin-top: 1rem;
   padding: 4rem 0;
   background-color: var(--vp-c-bg-soft);
}

.comparison-container {
   max-width: 960px;
   margin: 0 auto;
   padding: 0 24px;
}

.feature-comparison {
   margin-bottom: 2.5rem;
}

.feature-comparison h4 {
   display: flex;
   align-items: center;
   margin-bottom: 0.5rem;
   font-size: 1.1rem;
   font-weight: 600;
}

.badge {
   background-color: var(--vp-c-brand-1);
   color: white;
   padding: 0.2rem 0.6rem;
   border-radius: 6px;
   font-size: 0.8rem;
   margin-right: 0.75rem;
   letter-spacing: 0.02em;
}

.feature-comparison p {
   color: var(--vp-c-text-2);
   line-height: 1.6;
   margin: 0;
}
</style>
