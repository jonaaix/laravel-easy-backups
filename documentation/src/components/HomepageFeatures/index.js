import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

const FeatureList = [

   {
      title: 'Developer-First Experience',
      img: require('@site/static/img/developer.png').default,
      description: (
         <>
            Get started in minutes. The package encourages best practices by guiding you to create your own
            version-controlled Artisan commands for consistent and reliable backup automation.
         </>
      ),
   },
   {
      title: 'Intuitive & Fluent API',
      img: require('@site/static/img/api.png').default,
      description: (
         <>
            Designed with the developer in mind. Its clean and fluent API lets you define complex backup workflows—including
            databases, files, remote storage, and cleanup—in just a few readable lines of code.
         </>
      ),
   },
   {
      title: 'Robust & Reliable',
      img: require('@site/static/img/robust.png').default,
      description: (
         <>
            Built on the native command-line tools for MySQL, MariaDB, PostgreSQL, and SQLite to ensure maximum performance and
            reliability. With remote storage, cleanup policies, and built-in verification, your backups are safe.
         </>
      ),
   },
];

function Feature({ img, title, description }) {
   return (
      <div className={clsx('col col--4')}>
         <div className="text--center">
            <img className="feature__image" src={img} alt="Logo" role="img" />
         </div>
         <div className="text--center padding-horiz--md">
            <Heading as="h3">{title}</Heading>
            <p>{description}</p>
         </div>
      </div>
   );
}

export default function HomepageFeatures() {
   return (
      <section className={styles.features}>
         <div className="container" style={{ marginTop: '2rem' }}>
            <div className="row">
               {FeatureList.map((props, idx) => (
                  <Feature key={idx} {...props} />
               ))}
            </div>
         </div>
      </section>
   );
}
