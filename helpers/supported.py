import urllib
from bs4 import BeautifulSoup
import json

url = "https://rg3.github.io/youtube-dl/supportedsites.html"
page = urllib.request.urlopen(url).read()
soup = BeautifulSoup(page, 'html.parser')
sites = soup.find_all('li')
cleanSites = sorted(list(set([site.b.get_text().split(":")[0] for site in sites])))
with open('supported.json', 'w') as outfile:
    json.dump(cleanSites, outfile)
