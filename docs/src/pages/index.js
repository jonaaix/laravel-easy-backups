import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import HomepageFeatures from '@site/src/components/HomepageFeatures';

import Heading from '@theme/Heading';
import styles from './index.module.css';

function HomepageHeader() {
   const { siteConfig } = useDocusaurusContext();
   return (
      <header className={clsx('hero hero--primary', styles.heroBanner)}>
         <div className="container">
            <img className="hero__image" src={require('@site/static/img/logo2.png').default} alt="Logo" height={300} />
            <br />
            <Heading as="h1" className="hero__title">
               {siteConfig.title}
            </Heading>
            <p className="hero__subtitle">{siteConfig.tagline}</p>
            <div className={styles.buttons}>
               <Link className="button button--secondary button--lg" to="/docs/getting-started">
                  Documentation
               </Link>
            </div>
         </div>
      </header>
   );
}

export default function Home() {
   const { siteConfig } = useDocusaurusContext();
   return (
      <Layout title={`${siteConfig.title}`} description="Description will go into a meta tag in <head />">
         <HomepageHeader />
         <main>
            <HomepageFeatures />
            <section className={styles.comparisonSection}>
               <div className="container">
                  <div className="row">
                     <div className="col col--10 col--offset-1">
                        <hr className={styles.separator} />

                        <div className={styles.featureComparison}>
                           <h4><span className={styles.badge}>SUPERIOR</span> Fluent API</h4>
                           <p>Stop configuring backups in massive arrays. Define complex backup workflows with a clean, chainable, and expressive API that is a delight to read and write. Your backup logic now lives in version control as clean, understandable code.</p>
                        </div>

                        <div className={styles.featureComparison}>
                           <h4><span className={styles.badge}>BETTER</span> Automation & Reliability</h4>
                           <p>Built to be automated. The package encourages creating dedicated, reusable Artisan commands for your specific needs. Combined with native database tools and remote storage drivers, it provides a rock-solid foundation for your backup strategy.</p>
                        </div>

                        <div className={styles.featureComparison}>
                           <h4><span className={styles.badge}>SIMPLER</span> Complex Workflows</h4>
                           <p>Backing up databases, files, and directories together? Adding encryption and notifications? Setting up automatic cleanup policies? What used to be complex is now simple. Chain a few methods, and you're done. No magic, just a clean API.</p>
                        </div>

                     </div>
                  </div>
               </div>
            </section>
         </main>
      </Layout>
   );
}
