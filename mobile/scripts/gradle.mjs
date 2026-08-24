#!/usr/bin/env node
/**
 * Runs the Android Gradle wrapper on whichever platform is doing the building,
 * with a JDK the Android Gradle Plugin will actually accept.
 *
 * Two things go wrong otherwise. `./gradlew` is a Unix path that cmd.exe does
 * not understand, and Windows uses a separate `gradlew.bat`. And a machine that
 * has ever had an older Java installed usually still points JAVA_HOME at it,
 * which fails with "Dependency requires at least JVM runtime version 11" — a
 * message that says nothing about where a newer JVM might be found.
 *
 * So: pick the wrapper for this platform, find a JDK 17 or newer, and hand it
 * to Gradle explicitly. Android Studio bundles a suitable JDK, so on a machine
 * set up for Android development one is nearly always already present.
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const MOBILE_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const ANDROID_DIR = join(MOBILE_DIR, 'android');
const isWindows = process.platform === 'win32';
const MINIMUM_JDK = 17;

/* ------------------------------------------------------------------ JDK ---- */

/** Reads the major version a JDK reports, or null if it is not a usable JDK. */
function javaMajor(home) {
  if (!home) return null;
  const binary = join(home, 'bin', isWindows ? 'java.exe' : 'java');
  if (!existsSync(binary)) return null;

  // `java -version` writes to stderr, and JAVA_TOOL_OPTIONS can add a banner
  // line ahead of it, so both streams are read and the version line is matched
  // wherever it lands.
  const result = spawnSync(binary, ['-version'], { encoding: 'utf8' });
  const output = `${result.stdout ?? ''}${result.stderr ?? ''}`;
  const match = output.match(/version "(\d+)(?:\.(\d+))?/);
  if (!match) return null;

  // Java 8 and earlier report as 1.8.0; everything since reports its real major.
  const major = Number(match[1]);
  return major === 1 ? Number(match[2]) : major;
}

/** Every directory inside `parent` whose name looks like a JDK. */
function jdksIn(parent) {
  try {
    return readdirSync(parent, { withFileTypes: true })
      .filter((entry) => entry.isDirectory() || entry.isSymbolicLink())
      .map((entry) => join(parent, entry.name));
  } catch {
    return [];
  }
}

function candidateJdks() {
  const home = process.env.HOME || process.env.USERPROFILE || '';
  const local = process.env.LOCALAPPDATA || join(home, 'AppData', 'Local');
  const programFiles = process.env.ProgramFiles || 'C:\\Program Files';

  if (isWindows) {
    return [
      join(programFiles, 'Android', 'Android Studio', 'jbr'),
      join(local, 'Programs', 'Android Studio', 'jbr'),
      join(programFiles, 'Android', 'Android Studio Preview', 'jbr'),
      ...jdksIn(join(programFiles, 'Eclipse Adoptium')),
      ...jdksIn(join(programFiles, 'Microsoft')),
      ...jdksIn(join(programFiles, 'Java')),
      ...jdksIn(join(programFiles, 'Zulu')),
    ];
  }

  if (process.platform === 'darwin') {
    return [
      '/Applications/Android Studio.app/Contents/jbr/Contents/Home',
      join(home, 'Applications', 'Android Studio.app', 'Contents', 'jbr', 'Contents', 'Home'),
      ...jdksIn('/Library/Java/JavaVirtualMachines').map((p) => join(p, 'Contents', 'Home')),
      '/opt/homebrew/opt/openjdk/libexec/openjdk.jdk/Contents/Home',
    ];
  }

  return [
    '/opt/android-studio/jbr',
    join(home, 'android-studio', 'jbr'),
    ...jdksIn('/usr/lib/jvm'),
  ];
}

/** Returns a JAVA_HOME good enough for the Android Gradle Plugin, or null. */
function resolveJavaHome() {
  const configured = process.env.JAVA_HOME;
  const configuredMajor = javaMajor(configured);
  if (configuredMajor !== null && configuredMajor >= MINIMUM_JDK) {
    return { home: configured, major: configuredMajor, source: 'JAVA_HOME' };
  }

  for (const candidate of candidateJdks()) {
    const major = javaMajor(candidate);
    if (major !== null && major >= MINIMUM_JDK) {
      return { home: candidate, major, source: 'detected' };
    }
  }

  return null;
}

/* ------------------------------------------------------------------ SDK ---- */

/**
 * Gradle finds the Android SDK through `local.properties`, which Android Studio
 * writes the first time it opens a project. Building from the command line
 * without ever opening Studio therefore fails with "SDK location not found", so
 * the file is written here if the SDK is somewhere findable.
 */
function ensureLocalProperties() {
  const propertiesPath = join(ANDROID_DIR, 'local.properties');
  if (existsSync(propertiesPath)) return;
  if (process.env.ANDROID_HOME || process.env.ANDROID_SDK_ROOT) return;

  const home = process.env.HOME || process.env.USERPROFILE || '';
  const local = process.env.LOCALAPPDATA || join(home, 'AppData', 'Local');
  const candidates = isWindows
    ? [join(local, 'Android', 'Sdk')]
    : process.platform === 'darwin'
      ? [join(home, 'Library', 'Android', 'sdk')]
      : [join(home, 'Android', 'Sdk'), '/usr/lib/android-sdk'];

  const sdk = candidates.find((path) => existsSync(join(path, 'platform-tools')) || existsSync(join(path, 'platforms')));
  if (!sdk) return;

  // Forward slashes, because local.properties is a Java properties file where a
  // backslash starts an escape sequence.
  writeFileSync(propertiesPath, `sdk.dir=${sdk.replace(/\\/g, '/')}\n`, 'utf8');
  console.log(`Wrote android/local.properties pointing at ${sdk}`);
}

/* -------------------------------------------------------------- wrapper ---- */

const wrapper = join(ANDROID_DIR, isWindows ? 'gradlew.bat' : 'gradlew');

if (!existsSync(wrapper)) {
  console.error(
    'The Android project has not been created yet.\n' +
    'Run this first, from the mobile folder:\n\n' +
    '  npx cap add android\n'
  );
  process.exit(1);
}

const args = process.argv.slice(2);
if (args.length === 0) {
  console.error('Usage: node scripts/gradle.mjs <gradle task>');
  process.exit(1);
}

ensureLocalProperties();

const jdk = resolveJavaHome();

if (!jdk) {
  const current = javaMajor(process.env.JAVA_HOME);
  console.error(
    `\nAndroid needs a JDK ${MINIMUM_JDK} or newer to build.` +
    (current === null
      ? '\nNo usable JDK was found on this machine.'
      : `\nJAVA_HOME currently points at a Java ${current} installation, and no newer one was found.`) +
    '\n\nInstalling Android Studio provides one. If it is already installed,' +
    '\nset JAVA_HOME to the JDK it ships with:\n\n' +
    (isWindows
      ? '  setx JAVA_HOME "C:\\Program Files\\Android\\Android Studio\\jbr"\n' +
        '\nThen close this window and open a new one.\n'
      : '  export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"\n')
  );
  process.exit(1);
}

if (jdk.source === 'detected') {
  console.log(`Using JDK ${jdk.major} at ${jdk.home}`);
}

// Node refuses to spawn a .bat file without a shell, and a shell needs the path
// quoted in case the project sits under a folder with a space in its name.
const command = isWindows ? `"${wrapper}"` : wrapper;

const child = spawn(command, args, {
  cwd: ANDROID_DIR,
  stdio: 'inherit',
  shell: isWindows,
  env: { ...process.env, JAVA_HOME: jdk.home },
});

child.on('error', (error) => {
  console.error(`Could not start the Gradle wrapper: ${error.message}`);
  process.exit(1);
});

child.on('close', (code) => {
  if (code !== 0) {
    process.exit(code ?? 1);
  }

  const built = {
    assembleDebug: join(ANDROID_DIR, 'app', 'build', 'outputs', 'apk', 'debug', 'app-debug.apk'),
    assembleRelease: join(ANDROID_DIR, 'app', 'build', 'outputs', 'apk', 'release', 'app-release.apk'),
    bundleRelease: join(ANDROID_DIR, 'app', 'build', 'outputs', 'bundle', 'release', 'app-release.aab'),
  }[args[0]];

  if (built && existsSync(built)) {
    console.log(`\nBuilt: ${built}`);
  }
});
